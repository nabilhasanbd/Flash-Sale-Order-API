<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', ['credit', 'debit']);

            $table->decimal('amount', 10, 2);

            $table->decimal('balance_before', 10, 2);

            $table->decimal('balance_after', 10, 2);

            $table->string('reference')->unique();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};