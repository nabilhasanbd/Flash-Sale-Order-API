<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;
use App\Interfaces\WalletRepositoryInterface;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WalletService
{
    public function __construct(
        protected WalletRepositoryInterface $walletRepository,
    ) {}

    public function hasEnoughBalance(User $user, float $amount): bool
    {
        $wallet = $this->walletRepository->findByUser($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        return $wallet->balance >= $amount;
    }

    public function deductInTransaction(
        User $user,
        float $amount,
        int $orderId,
        string $description = 'Flash Sale Purchase'
    ): array {
        $wallet = $this->walletRepository->lockWallet($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        $balanceBefore = (float) $wallet->balance;

        if ($balanceBefore < $amount) {
            throw new InsufficientBalanceException();
        }

        $newBalance = $balanceBefore - $amount;

        $this->walletRepository->updateBalance($wallet, $newBalance);

        $transaction = $this->walletRepository->createTransaction([
            'wallet_id' => $wallet->id,
            'order_id' => $orderId,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'reference' => $this->generateReference(),
            'description' => $description,
        ]);

        return [
            'wallet' => $wallet->fresh(),
            'transaction' => $transaction,
        ];
    }

    public function deduct(
        User $user,
        float $amount,
        ?Order $order = null,
        string $description = 'Flash Sale Purchase'
    ): Wallet {
        $wallet = $this->walletRepository->lockWallet($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        $balanceBefore = (float) $wallet->balance;

        if ($balanceBefore < $amount) {
            throw new InsufficientBalanceException();
        }

        $newBalance = $balanceBefore - $amount;

        $this->walletRepository->updateBalance($wallet, $newBalance);

        $this->walletRepository->createTransaction([
            'wallet_id' => $wallet->id,
            'order_id' => $order?->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'reference' => $this->generateReference(),
            'description' => $description,
        ]);

        $wallet->refresh();

        return $wallet;
    }

    public function credit(
        User $user,
        float $amount,
        string $description = 'Wallet Credit'
    ): Wallet {
        $wallet = $this->walletRepository->findByUser($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        $balanceBefore = (float) $wallet->balance;
        $newBalance = $balanceBefore + $amount;

        $this->walletRepository->updateBalance($wallet, $newBalance);

        $this->walletRepository->createTransaction([
            'wallet_id' => $wallet->id,
            'order_id' => null,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'reference' => $this->generateReference(),
            'description' => $description,
        ]);

        $wallet->refresh();

        return $wallet;
    }

    public function getBalance(User $user): float
    {
        $wallet = $this->walletRepository->findByUser($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        return (float) $wallet->balance;
    }

    public function getStatement(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $wallet = $this->walletRepository->findByUser($user);

        if ($wallet === null) {
            throw new WalletNotFoundException();
        }

        return $this->walletRepository->getTransactions($wallet, $perPage);
    }

    private function generateReference(): string
    {
        return 'WTX-'.date('Ymd').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}