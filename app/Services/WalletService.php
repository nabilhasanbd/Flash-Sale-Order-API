<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidAmountException;
use App\Exceptions\InvalidTransferException;
use App\Exceptions\SelfTransferException;
use App\Exceptions\WalletNotFoundException;
use App\Interfaces\WalletRepositoryInterface;
use App\Interfaces\WalletTransactionRepositoryInterface;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        protected WalletRepositoryInterface $walletRepository,
        protected WalletTransactionRepositoryInterface $walletTransactionRepository,
    ) {}

    /**
     * Move funds from the customer wallet to the merchant wallet and record
     * a balanced double-entry ledger under the given payment transaction.
     *
     * The caller (OrderService) owns the database transaction; this method
     * only locks rows and performs writes within it.
     *
     * @return array{
     *     customer_wallet: Wallet,
     *     merchant_wallet: Wallet,
     *     debit: WalletTransaction,
     *     credit: WalletTransaction,
     * }
     */
    public function transfer(
        Wallet $customerWallet,
        Wallet $merchantWallet,
        float $amount,
        PaymentTransaction $paymentTransaction,
        string $description
    ): array {
        $this->validateTransfer($customerWallet, $merchantWallet, $amount, $paymentTransaction);

        // 1. Lock the customer wallet.
        $customerLocked = $this->walletRepository->lockForUpdate($customerWallet);
        if ($customerLocked === null) {
            throw new WalletNotFoundException;
        }

        // 2. Lock the merchant wallet.
        $merchantLocked = $this->walletRepository->lockForUpdate($merchantWallet);
        if ($merchantLocked === null) {
            throw new WalletNotFoundException;
        }

        // 3. Check the customer balance (never allow negative balances).
        $customerBalanceBefore = (float) $customerLocked->balance;
        if ($customerBalanceBefore < $amount) {
            throw new InsufficientBalanceException;
        }

        $merchantBalanceBefore = (float) $merchantLocked->balance;

        // 4. Debit the customer wallet.
        $customerBalanceAfter = $customerBalanceBefore - $amount;
        $this->walletRepository->updateBalance($customerLocked, $customerBalanceAfter);

        // 5. Credit the merchant wallet.
        $merchantBalanceAfter = $merchantBalanceBefore + $amount;
        $this->walletRepository->updateBalance($merchantLocked, $merchantBalanceAfter);

        $sharedReference = $this->generateReference();

        // 6. Create the debit ledger entry on the customer wallet.
        $debit = $this->walletTransactionRepository->create([
            'wallet_id' => $customerLocked->id,
            'payment_transaction_id' => $paymentTransaction->id,
            'type' => WalletTransactionType::Debit,
            'amount' => $amount,
            'balance_before' => $customerBalanceBefore,
            'balance_after' => $customerBalanceAfter,
            'reference' => $sharedReference.'-DR',
            'description' => $description,
            'metadata' => [
                'transfer_reference' => $sharedReference,
                'payment_transaction_reference' => $paymentTransaction->reference,
                'counterparty_wallet_id' => $merchantLocked->id,
            ],
        ]);

        // 7. Create the credit ledger entry on the merchant wallet.
        $credit = $this->walletTransactionRepository->create([
            'wallet_id' => $merchantLocked->id,
            'payment_transaction_id' => $paymentTransaction->id,
            'type' => WalletTransactionType::Credit,
            'amount' => $amount,
            'balance_before' => $merchantBalanceBefore,
            'balance_after' => $merchantBalanceAfter,
            'reference' => $sharedReference.'-CR',
            'description' => $description,
            'metadata' => [
                'transfer_reference' => $sharedReference,
                'payment_transaction_reference' => $paymentTransaction->reference,
                'counterparty_wallet_id' => $customerLocked->id,
            ],
        ]);

        // 8. Return the updated wallets.
        return [
            'customer_wallet' => $customerLocked->fresh(),
            'merchant_wallet' => $merchantLocked->fresh(),
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    public function refund(
        Wallet $merchantWallet,
        Wallet $customerWallet,
        float $amount,
        PaymentTransaction $paymentTransaction,
        string $description = 'Refund'
    ): array {
        // A refund is a transfer in the opposite direction. Using the same
        // architecture guarantees a balanced double-entry ledger linked to a
        // single payment transaction id.
        return $this->transfer(
            $merchantWallet,
            $customerWallet,
            $amount,
            $paymentTransaction,
            $description
        );
    }

    public function hasEnoughBalance(User $user, float $amount): bool
    {
        $wallet = $this->getWalletForUser($user);

        return (float) $wallet->balance >= $amount;
    }

    public function getBalance(User $user): float
    {
        return (float) $this->getWalletForUser($user)->balance;
    }

    public function getStatement(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $wallet = $this->getWalletForUser($user);

        return $this->walletTransactionRepository->paginateForWallet($wallet, $perPage);
    }

    public function getLedgerForPaymentTransaction(PaymentTransaction $paymentTransaction): Collection
    {
        return $this->walletTransactionRepository->findByPaymentTransaction($paymentTransaction->id);
    }

    protected function getWalletForUser(User $user): Wallet
    {
        $wallet = $this->walletRepository->findByUser($user);

        if ($wallet === null) {
            throw new WalletNotFoundException;
        }

        return $wallet;
    }

    protected function validateTransfer(
        Wallet $customerWallet,
        Wallet $merchantWallet,
        float $amount,
        PaymentTransaction $paymentTransaction
    ): void {
        if ($amount <= 0) {
            throw new InvalidAmountException;
        }

        if ($customerWallet->is($merchantWallet)) {
            throw new SelfTransferException;
        }

        if ($paymentTransaction->status === PaymentTransactionStatus::Failed
            || $paymentTransaction->status === PaymentTransactionStatus::Reversed
        ) {
            throw new InvalidTransferException('The payment transaction is not usable.');
        }
    }

    protected function generateReference(): string
    {
        return 'WTX-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
    }
}
