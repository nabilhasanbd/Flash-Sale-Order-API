<?php

namespace App\Services;

use App\Interfaces\CouponRepositoryInterface;
use App\Interfaces\OrderRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\WalletRepositoryInterface;
use App\Events\OrderPlaced;
use App\Exceptions\DuplicatePurchaseException;
use App\Exceptions\FlashSaleNotActiveException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MaximumQuantityExceededException;
use App\Exceptions\ProductInactiveException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected ProductRepositoryInterface $productRepository,
        protected WalletRepositoryInterface $walletRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected CouponService $couponService,
    ) {}

    public function placeOrder(
        User $user,
        int $productId,
        int $quantity,
        ?string $couponCode = null
    ): Order {
        $subtotal = 0;
        $discount = 0;
        $coupon = null;

        if ($couponCode !== null) {
            $coupon = $this->couponService->validateCoupon($couponCode, 0);
            $discountData = $this->couponService->calculateDiscount($coupon, $subtotal);
            $discount = (float) $discountData['discount'];
        }

        $order = DB::transaction(function () use (
            $user,
            $productId,
            $quantity,
            $coupon,
            $discount,
            $couponCode
        ) {
            $product = $this->productRepository->findProductByIdForUpdate($productId);

            if ($product === null) {
                throw new \Exception('Product not found.');
            }

            if (!$product->isActive()) {
                throw new ProductInactiveException();
            }

            if (!$product->isFlashSaleRunning()) {
                throw new FlashSaleNotActiveException();
            }

            $maxQuantity = $product->flash_sale_max_quantity_per_order ?? 3;
            if ($quantity > $maxQuantity) {
                throw new MaximumQuantityExceededException($maxQuantity, $quantity);
            }

            $availableStock = (int) $product->available_stock;

            if ($availableStock < $quantity) {
                throw new InsufficientStockException();
            }

            $unitPrice = (float) $product->price;
            $subtotal = $unitPrice * $quantity;

            $totalAmount = $subtotal;

            if ($coupon !== null) {
                $discountData = $this->couponService->calculateDiscount($coupon, $subtotal);
                $discount = (float) $discountData['discount'];
                $totalAmount = $subtotal - $discount;
            }

            $existingOrder = $this->orderRepository->findByUserAndProduct($user->id, $productId);

            if ($existingOrder !== null) {
                throw new DuplicatePurchaseException();
            }

            $wallet = $this->walletRepository->lockWallet($user);

            if ($wallet === null) {
                throw new \Exception('Wallet not found.');
            }

            $walletBalance = (float) $wallet->balance;

            if ($walletBalance < $totalAmount) {
                throw new InsufficientBalanceException();
            }

            $newWalletBalance = $walletBalance - $totalAmount;

            $this->walletRepository->updateBalance($wallet, $newWalletBalance);

            $order = $this->orderRepository->createOrder([
                'user_id' => $user->id,
                'coupon_id' => $coupon?->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $totalAmount,
                'payment_status' => 'paid',
                'status' => 'completed',
            ]);

            $orderData = [
                'order_id' => $order->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];

            $this->orderRepository->createOrderItem($orderData);

            $this->orderRepository->decrementStock($product, $quantity);

            if ($coupon !== null) {
                $this->couponRepository->incrementUsage($coupon->id);
            }

            $this->walletRepository->createTransaction([
                'wallet_id' => $wallet->id,
                'order_id' => $order->id,
                'type' => 'debit',
                'amount' => $totalAmount,
                'balance_before' => $walletBalance,
                'balance_after' => $newWalletBalance,
                'reference' => $this->generateTransactionReference(),
                'description' => 'Flash Sale Purchase',
            ]);

            return $order;
        });

        DB::afterCommit(function () use ($order) {
            event(new OrderPlaced($order));
        });

        return $order;
    }

    private function generateTransactionReference(): string
    {
        return 'WTX-'.date('Ymd').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}