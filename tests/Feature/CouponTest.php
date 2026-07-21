<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Exceptions\CouponExpiredException;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InactiveCouponException;
use App\Exceptions\MinimumPurchaseException;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
    }

    public function test_calculates_percentage_discount(): void
    {
        $coupon = Coupon::factory()->percentage(25, 0)->create();

        $result = $this->couponService->calculateDiscount($coupon, 200);

        $this->assertSame('200.00', $result['subtotal']);
        $this->assertSame('50.00', $result['discount']);
        $this->assertSame('150.00', $result['final_amount']);
    }

    public function test_calculates_fixed_amount_discount(): void
    {
        $coupon = Coupon::factory()->fixed(75, 0)->create();

        $result = $this->couponService->calculateDiscount($coupon, 200);

        $this->assertSame('200.00', $result['subtotal']);
        $this->assertSame('75.00', $result['discount']);
        $this->assertSame('125.00', $result['final_amount']);
    }

    public function test_discount_never_exceeds_subtotal(): void
    {
        $coupon = Coupon::factory()->fixed(500, 0)->create();

        $result = $this->couponService->calculateDiscount($coupon, 100);

        $this->assertSame('100.00', $result['discount']);
        $this->assertSame('0.00', $result['final_amount']);
    }

    public function test_validates_valid_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10, 100)->create();

        $validated = $this->couponService->validateCoupon($coupon->code, 200);

        $this->assertTrue($validated->is($coupon));
    }

    public function test_rejects_unknown_coupon(): void
    {
        $this->expectException(CouponNotFoundException::class);

        $this->couponService->validateCoupon('NOPE', 200);
    }

    public function test_rejects_inactive_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10, 0)->create(['status' => false]);

        $this->expectException(InactiveCouponException::class);

        $this->couponService->validateCoupon($coupon->code, 200);
    }

    public function test_rejects_expired_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10, 0)->create(['expires_at' => now()->subDay()]);

        $this->expectException(CouponExpiredException::class);

        $this->couponService->validateCoupon($coupon->code, 200);
    }

    public function test_rejects_coupon_at_usage_limit(): void
    {
        $coupon = Coupon::factory()->percentage(10, 0)->create([
            'usage_limit' => 5,
            'used_count' => 5,
        ]);

        $this->expectException(CouponUsageLimitExceededException::class);

        $this->couponService->validateCoupon($coupon->code, 200);
    }

    public function test_rejects_coupon_below_minimum_purchase(): void
    {
        $coupon = Coupon::factory()->percentage(10, 500)->create();

        $this->expectException(MinimumPurchaseException::class);

        $this->couponService->validateCoupon($coupon->code, 100);
    }

    public function test_increment_usage_persists(): void
    {
        $coupon = Coupon::factory()->create(['used_count' => 2]);

        $this->couponService->incrementUsage($coupon->id);

        $this->assertSame(3, (int) $coupon->fresh()->used_count);
    }

    public function test_coupon_types_enum(): void
    {
        $this->assertSame('fixed', CouponType::Fixed->value);
        $this->assertSame('percentage', CouponType::Percentage->value);
    }
}
