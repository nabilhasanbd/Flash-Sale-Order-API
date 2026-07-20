<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'orders_user_product_active_unique';

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('coupon_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Partial unique index: a user may hold at most ONE active order for a
        // given product. Cancelled and failed orders are excluded so customers
        // can legitimately rebuy after a cancellation/failure. Rows with a NULL
        // product_id (or excluded statuses) are ignored by the index.
        DB::statement(
            'CREATE UNIQUE INDEX '.$this::INDEX_NAME.' '
            .'ON orders (user_id, product_id) '
            ."WHERE status NOT IN ('cancelled', 'failed')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.$this::INDEX_NAME);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
