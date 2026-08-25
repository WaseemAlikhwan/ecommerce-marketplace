<?php

namespace Tests\Feature;

use App\Coupons\CouponLineCandidate;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Exceptions\CouponException;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CouponsCpnATest extends TestCase
{
    use RefreshDatabase;

    private CouponService $coupons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->coupons = app(CouponService::class);
    }

    public function test_platform_percent_quotes_and_allocates_across_vendors(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendorUser()->vendor;
        $vendorB = $this->createVendorUser()->vendor;

        $coupon = Coupon::factory()->platform()->percent(10)->create([
            'code' => 'SAVE10',
            'currency_code' => 'SYP',
        ]);

        $lines = [
            new CouponLineCandidate(1, (int) $vendorA->id, null, 'SYP', 600),
            new CouponLineCandidate(2, (int) $vendorB->id, null, 'SYP', 400),
        ];

        $quote = $this->coupons->validateAndQuote($user, 'save10', $lines);

        $this->assertSame($coupon->id, $quote->couponId);
        $this->assertSame('SAVE10', $quote->code);
        $this->assertSame(CouponScope::Platform->value, $quote->scope);
        $this->assertNull($quote->vendorId);
        $this->assertSame(1000, $quote->eligibleSubtotalMinor);
        $this->assertSame(100, $quote->discountTotalMinor);
        $this->assertSame(60, $quote->discountByVendorId[(int) $vendorA->id]);
        $this->assertSame(40, $quote->discountByVendorId[(int) $vendorB->id]);

        $payload = $quote->toArray();
        $this->assertArrayNotHasKey('sku', $payload);
        $this->assertArrayNotHasKey('quantity', $payload);
        $json = json_encode($payload);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"sku"', $json);
        $this->assertStringNotContainsString('quantity', $json);
    }

    public function test_vendor_coupon_discounts_only_that_vendor_lines(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendorUser()->vendor;
        $vendorB = $this->createVendorUser()->vendor;

        Coupon::factory()->forVendor($vendorA)->percent(20)->create([
            'code' => 'SHOPA5',
            'currency_code' => 'SYP',
        ]);

        $lines = [
            new CouponLineCandidate(1, (int) $vendorA->id, null, 'SYP', 500),
            new CouponLineCandidate(2, (int) $vendorB->id, null, 'SYP', 500),
        ];

        $quote = $this->coupons->validateAndQuote($user, 'SHOPA5', $lines);

        $this->assertSame(CouponScope::Vendor->value, $quote->scope);
        $this->assertSame((int) $vendorA->id, $quote->vendorId);
        $this->assertSame(500, $quote->eligibleSubtotalMinor);
        $this->assertSame(100, $quote->discountTotalMinor);
        $this->assertSame([((int) $vendorA->id) => 100], $quote->discountByVendorId);
        $this->assertArrayNotHasKey((int) $vendorB->id, $quote->discountByVendorId);
    }

    public function test_second_distinct_code_conflicts_with_already_applied(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendorUser()->vendor;

        Coupon::factory()->platform()->percent(10)->create(['code' => 'SAVE10']);
        Coupon::factory()->forVendor($vendor)->percent(5)->create(['code' => 'SHOPA5']);

        $lines = [
            new CouponLineCandidate(1, (int) $vendor->id, null, 'SYP', 1000),
        ];

        try {
            $this->coupons->validateAndQuote($user, 'SHOPA5', $lines, alreadyAppliedCode: 'SAVE10');
            $this->fail('Expected single-code conflict');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::CONFLICT, $e->errorCode);
        }

        $this->coupons->assertSingleCodeAllowed('SAVE10', 'SAVE10');
    }

    public function test_min_eligible_not_met_fails_closed(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendorUser()->vendor;

        Coupon::factory()->platform()->fixed(50)->create([
            'code' => 'FLAT50',
            'min_eligible_amount_minor' => 1000,
        ]);

        $lines = [
            new CouponLineCandidate(1, (int) $vendor->id, null, 'SYP', 500),
        ];

        try {
            $this->coupons->validateAndQuote($user, 'FLAT50', $lines);
            $this->fail('Expected min not met');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::MIN_NOT_MET, $e->errorCode);
        }
    }

    public function test_expired_and_usage_limits_fail_closed(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendorUser()->vendor;
        $lines = [
            new CouponLineCandidate(1, (int) $vendor->id, null, 'SYP', 2000),
        ];

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'OLD10',
            'ends_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);

        try {
            $this->coupons->validateAndQuote(
                $user,
                'OLD10',
                $lines,
                at: Carbon::parse('2026-08-25 12:00:00'),
            );
            $this->fail('Expected expired');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::EXPIRED, $e->errorCode);
        }

        $limited = Coupon::factory()->platform()->percent(10)->create([
            'code' => 'ONCE',
            'global_usage_limit' => 1,
            'per_user_usage_limit' => 1,
        ]);

        CouponRedemption::factory()->create([
            'coupon_id' => $limited->id,
            'user_id' => $user->id,
            'discount_amount_minor' => 10,
            'currency_code' => 'SYP',
            'status' => CouponRedemptionStatus::Active,
        ]);

        try {
            $this->coupons->validateAndQuote($user, 'ONCE', $lines);
            $this->fail('Expected usage limit');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::LIMIT, $e->errorCode);
        }
    }

    public function test_currency_mismatch_and_inactive_fail_closed(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendorUser()->vendor;

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'USDONLY',
            'currency_code' => 'USD',
        ]);

        try {
            $this->coupons->validateAndQuote($user, 'USDONLY', [
                new CouponLineCandidate(1, (int) $vendor->id, null, 'SYP', 1000),
            ]);
            $this->fail('Expected currency failure');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::CURRENCY, $e->errorCode);
        }

        Coupon::factory()->platform()->percent(10)->inactive()->create([
            'code' => 'DEAD',
        ]);

        try {
            $this->coupons->validateAndQuote($user, 'DEAD', [
                new CouponLineCandidate(1, (int) $vendor->id, null, 'SYP', 1000),
            ]);
            $this->fail('Expected inactive');
        } catch (CouponException $e) {
            $this->assertSame(CouponException::INACTIVE, $e->errorCode);
        }
    }

    public function test_product_allowlist_and_max_cap(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendorUser()->vendor;
        $category = Category::factory()->create();
        $allowed = Product::factory()->create([
            'store_id' => $vendor->store->id,
            'category_id' => $category->id,
            'currency_code' => 'SYP',
        ]);
        $other = Product::factory()->create([
            'store_id' => $vendor->store->id,
            'currency_code' => 'SYP',
        ]);

        $coupon = Coupon::factory()->platform()->percent(50)->create([
            'code' => 'CAT50',
            'max_discount_amount_minor' => 100,
        ]);
        $coupon->products()->attach($allowed->id);

        $quote = $this->coupons->validateAndQuote($user, 'CAT50', [
            new CouponLineCandidate((int) $allowed->id, (int) $vendor->id, (int) $category->id, 'SYP', 1000),
            new CouponLineCandidate((int) $other->id, (int) $vendor->id, null, 'SYP', 1000),
        ]);

        $this->assertSame(1000, $quote->eligibleSubtotalMinor);
        $this->assertSame(100, $quote->discountTotalMinor);
        $this->assertSame(CouponType::Percent, $coupon->fresh()->type);
    }

    public function test_coupon_code_is_unique(): void
    {
        Coupon::factory()->create(['code' => 'UNIQUE1']);

        $this->expectException(QueryException::class);
        Coupon::factory()->create(['code' => 'UNIQUE1']);
    }
}
