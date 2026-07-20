<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\UserRole;
use App\Exceptions\WalletNotFoundException;
use App\Interfaces\PaymentTransactionRepositoryInterface;
use App\Interfaces\WalletRepositoryInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

class PaymentTransactionService
{
    public function __construct(
        protected PaymentTransactionRepositoryInterface $paymentTransactionRepository,
        protected WalletRepositoryInterface $walletRepository,
    ) {}

    /**
     * Create a payment transaction in the pending state and bind it to the
     * customer and merchant wallets. No money is moved here; the caller
     * (OrderService) owns the database transaction and drives the transfer.
     */
    public function createPending(
        Order $order,
        User $customer,
        float $amount,
        ?int $merchantId,
        array $metadata = [],
    ): PaymentTransaction {
        $customerWallet = $this->walletRepository->findByUser($customer);
        if ($customerWallet === null) {
            throw new WalletNotFoundException;
        }

        $merchantWallet = $this->resolveMerchantWallet($merchantId);

        return $this->paymentTransactionRepository->create([
            'reference' => $this->generateReference(),
            'order_id' => $order->id,
            'customer_wallet_id' => $customerWallet->id,
            'merchant_wallet_id' => $merchantWallet->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Wallet,
            'transaction_type' => PaymentTransactionType::FlashSale,
            'status' => PaymentTransactionStatus::Pending,
            'metadata' => array_merge([
                'merchant_id' => $merchantId,
            ], $metadata),
        ]);
    }

    public function markSuccess(PaymentTransaction $paymentTransaction): PaymentTransaction
    {
        $this->paymentTransactionRepository->updateStatus(
            $paymentTransaction,
            PaymentTransactionStatus::Success->value
        );

        return $paymentTransaction->fresh();
    }

    public function markFailed(PaymentTransaction $paymentTransaction): PaymentTransaction
    {
        $this->paymentTransactionRepository->updateStatus(
            $paymentTransaction,
            PaymentTransactionStatus::Failed->value
        );

        return $paymentTransaction->fresh();
    }

    public function markRefunded(PaymentTransaction $paymentTransaction): PaymentTransaction
    {
        $this->paymentTransactionRepository->updateStatus(
            $paymentTransaction,
            PaymentTransactionStatus::Refunded->value
        );

        return $paymentTransaction->fresh();
    }

    public function findByReference(string $reference): ?PaymentTransaction
    {
        return $this->paymentTransactionRepository->findByReference($reference);
    }

    /**
     * Resolve the wallet of the product's merchant. Falls back to the first
     * merchant wallet when the product has no explicit merchant, so the ledger
     * is always balanced (customer debit / merchant credit).
     */
    protected function resolveMerchantWallet(?int $merchantId): Wallet
    {
        if ($merchantId !== null) {
            $merchant = User::find($merchantId);

            if ($merchant !== null) {
                $wallet = $this->walletRepository->findByUser($merchant);

                if ($wallet !== null) {
                    return $wallet;
                }
            }
        }

        $wallet = Wallet::whereHas('user', function ($query) {
            $query->where('role', UserRole::Merchant->value);
        })->first();

        if ($wallet === null) {
            throw new WalletNotFoundException;
        }

        return $wallet;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'TXN-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->paymentTransactionRepository->findByReference($reference) !== null);

        return $reference.Str::upper(Str::random(2));
    }
}
