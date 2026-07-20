<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
            $table->dropUnique(['reference']);
            $table->dropColumn('updated_at');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->after('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->json('metadata')
                ->nullable()
                ->after('description');

            $table->index('payment_transaction_id');
        });

        DB::statement(
            'ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_amount_check CHECK (amount >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_amount_check');

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_transaction_id']);
            $table->dropIndex(['reference']);
            $table->dropForeign(['payment_transaction_id']);
            $table->dropColumn('payment_transaction_id');
            $table->dropColumn('metadata');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique('reference');
            $table->timestamp('updated_at')->nullable();
        });
    }
};
