<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'reference',
        'order_id',
        'customer_wallet_id',
        'merchant_wallet_id',
        'amount',
        'payment_method',
        'transaction_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'transaction_type' => PaymentTransactionType::class,
        'status' => PaymentTransactionStatus::class,
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'customer_wallet_id');
    }

    public function merchantWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'merchant_wallet_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function debitEntry(): ?WalletTransaction
    {
        return $this->walletTransactions()->where('type', WalletTransactionType::Debit)->first();
    }

    public function creditEntry(): ?WalletTransaction
    {
        return $this->walletTransactions()->where('type', WalletTransactionType::Credit)->first();
    }

    public function isBalanced(): bool
    {
        $debit = $this->walletTransactions()->where('type', WalletTransactionType::Debit)->count();
        $credit = $this->walletTransactions()->where('type', WalletTransactionType::Credit)->count();

        return $debit === 1 && $credit === 1;
    }
}
