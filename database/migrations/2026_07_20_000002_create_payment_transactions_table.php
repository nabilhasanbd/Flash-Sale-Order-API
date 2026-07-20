<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('customer_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->foreignId('merchant_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('payment_method')->default('wallet');

            $table->string('transaction_type');

            $table->string('status')->default('pending');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('customer_wallet_id');
            $table->index('merchant_wallet_id');
            $table->index('status');
            $table->index('transaction_type');
            $table->index(['status', 'transaction_type']);
        });

        DB::statement(
            'ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_amount_check CHECK (amount >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_transactions DROP CONSTRAINT IF EXISTS payment_transactions_amount_check');

        Schema::dropIfExists('payment_transactions');
    }
};
