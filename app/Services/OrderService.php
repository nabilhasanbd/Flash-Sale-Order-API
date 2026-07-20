<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Exceptions\DuplicatePurchaseException;
use App\Exceptions\FlashSaleNotActiveException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MaximumQuantityExceededException;
use App\Exceptions\ProductInactiveException;
use App\Interfaces\OrderRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected ProductRepositoryInterface $productRepository,
        protected CouponService $couponService,
        protected PaymentTransactionService $paymentTransactionService,
        protected WalletService $walletService,
    ) {}

    public function placeOrder(
        User $user,
        int $productId,
        int $quantity,
        ?string $couponCode = null
    ): Order {
        // ---- Read-only validation (no locks, no transaction yet) ----
        $product = $this->productRepository->findProduct($productId);

        if ($product === null) {
            throw new \Exception('Product not found.');
        }

        $this->validateProduct($product);

        $this->validateQuantity($product, $quantity);

        [$coupon, $subtotal, $discount, $totalAmount] = $this->resolvePricing($product, $quantity, $couponCode);

        // ---- Critical section: OrderService is the sole owner of the DB transaction ----
        try {
            $order = DB::transaction(function () use (
                $user,
                $product,
                $quantity,
                $coupon,
                $subtotal,
                $discount,
                $totalAmount
            ) {
                // Lock product for update and reload it to read the live stock.
                $lockedProduct = $this->productRepository->findProductByIdForUpdate($product->id);

                if ($lockedProduct === null) {
                    throw new \Exception('Product not found.');
                }

                // Re-check stock under the lock to prevent overselling.
                if ((int) $lockedProduct->available_stock < $quantity) {
                    throw new InsufficientStockException;
                }

                // Duplicate-purchase guard: checked AFTER acquiring the product row
                // lock so concurrent requests for the same product serialize, and the
                // second one observes the first request's committed order.
                if ($this->orderRepository->hasActivePurchaseForProduct($user->id, $lockedProduct->id)) {
                    throw new DuplicatePurchaseException;
                }

                $unitPrice = (float) $lockedProduct->price;

                // Create the order in a pending state; it is only finalised on success.
                $order = $this->orderRepository->createOrder([
                    'user_id' => $user->id,
                    'product_id' => $lockedProduct->id,
                    'coupon_id' => $coupon?->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $totalAmount,
                    'payment_status' => PaymentStatus::Pending,
                    'status' => OrderStatus::Pending,
                ]);

                $this->orderRepository->createOrderItem([
                    'order_id' => $order->id,
                    'product_id' => $lockedProduct->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                // Create the PaymentTransaction (status = pending) bound to the wallets.
                $paymentTransaction = $this->paymentTransactionService->createPending(
                    $order,
                    $user,
                    $totalAmount,
                    $lockedProduct->merchant_id
                );

                // Move funds: customer debit -> merchant credit, with a double-entry ledger.
                $this->walletService->transfer(
                    $paymentTransaction->customerWallet,
                    $paymentTransaction->merchantWallet,
                    $totalAmount,
                    $paymentTransaction,
                    'Flash Sale Purchase - Order #'.$order->id
                );

                // Reduce product stock.
                $this->orderRepository->decrementStock($lockedProduct, $quantity);

                // Increase coupon usage.
                if ($coupon !== null) {
                    $this->couponService->incrementUsage($coupon->id);
                }

                // Mark the payment successful.
                $this->paymentTransactionService->markSuccess($paymentTransaction);

                // Finalise the order.
                $this->orderRepository->updateOrderStatus(
                    $order,
                    OrderStatus::Completed,
                    PaymentStatus::Paid
                );

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Database-level backstop: if two requests slipped past the
            // application check, the partial unique index rejects the insert.
            // Convert it into a clean domain exception (HTTP 409).
            if (str_contains($e->getMessage(), 'orders_user_product_active_unique')) {
                throw new DuplicatePurchaseException;
            }

            throw $e;
        }

        // Dispatch only after the transaction has committed.
        DB::afterCommit(function () use ($order) {
            event(new OrderPlaced($order));
        });

        return $order->fresh()->load([
            'orderItems.product',
            'paymentTransaction.walletTransactions',
        ]);
    }

    protected function validateProduct(Product $product): void
    {
        if (! $product->isActive()) {
            throw new ProductInactiveException;
        }

        if (! $product->isFlashSaleRunning()) {
            throw new FlashSaleNotActiveException;
        }
    }

    protected function validateQuantity(Product $product, int $quantity): void
    {
        $maxQuantity = $product->flash_sale_max_quantity_per_order ?? 3;

        if ($quantity > $maxQuantity) {
            throw new MaximumQuantityExceededException($maxQuantity, $quantity);
        }

        if ((int) $product->available_stock < $quantity) {
            throw new InsufficientStockException;
        }
    }

    /**
     * Validate the coupon (if any) and calculate the final amount.
     *
     * @return array{0: ?Coupon, 1: float, 2: float, 3: float}
     */
    protected function resolvePricing(Product $product, int $quantity, ?string $couponCode): array
    {
        $subtotal = (float) $product->price * $quantity;
        $discount = 0.0;
        $coupon = null;

        if ($couponCode !== null) {
            $coupon = $this->couponService->validateCoupon($couponCode, $subtotal);
            $discountData = $this->couponService->calculateDiscount($coupon, $subtotal);
            $discount = (float) $discountData['discount'];
        }

        return [$coupon, $subtotal, $discount, $subtotal - $discount];
    }
}
