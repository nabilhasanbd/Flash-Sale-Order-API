<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original users migration restricted role to ['admin', 'customer'],
        // but the UserRole enum, the products.merchant_id relation, and the
        // wallet-transfer flow all require a 'merchant' role. Relax the check
        // constraint to allow it.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_role_check '.
            "CHECK (role IN ('admin', 'customer', 'merchant'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_role_check '.
            "CHECK (role IN ('admin', 'customer'))"
        );
    }
};
