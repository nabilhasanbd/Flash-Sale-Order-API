<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->enum('type', ['fixed', 'percentage']);

            $table->decimal('value', 10, 2);

            $table->decimal('minimum_purchase', 10, 2)->default(0);

            $table->unsignedInteger('usage_limit');

            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('expires_at');

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
