<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Support\Locale;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpsAdmCTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            CommissionSettingSeeder::class,
        ]);
    }

    public function test_guest_is_redirected_to_login_for_ops_screens(): void
    {
        [$parent, $vendorOrder, $payment] = $this->seedReadableOrderGraph();

        foreach ([
            route('admin.orders'),
            route('admin.orders.show', $parent),
            route('admin.vendor-orders.index'),
            route('admin.vendor-orders.show', $vendorOrder),
            route('admin.payments.index'),
            route('admin.payments.show', $payment),
            route('admin.users.index'),
            route('admin.settings'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_non_staff_receives_forbidden_for_ops_screens(): void
    {
        [$parent, $vendorOrder, $payment] = $this->seedReadableOrderGraph();
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();

        foreach ([$customer, $vendor] as $actor) {
            foreach ([
                route('admin.orders'),
                route('admin.orders.show', $parent),
                route('admin.vendor-orders.index'),
                route('admin.vendor-orders.show', $vendorOrder),
                route('admin.payments.index'),
                route('admin.payments.show', $payment),
                route('admin.users.index'),
                route('admin.settings'),
            ] as $url) {
                $this->actingAs($actor)->get($url)->assertForbidden();
            }
        }
    }

    public function test_staff_can_view_parent_vendor_payment_users_and_commission(): void
    {
        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        [$parent, $vendorOrder, $payment, $sku] = $this->seedReadableOrderGraph();

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSeeText($parent->public_code)
            ->assertDontSee($sku);

        $parentShow = $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.orders.show', $parent));

        $parentShow->assertOk();
        $parentShow->assertSeeText($parent->public_code);
        $parentShow->assertSeeText($vendorOrder->public_code);
        $parentShow->assertSeeText('Test Product');
        $parentShow->assertSeeText('COD pending');
        $parentShow->assertDontSee($sku);
        $parentShow->assertDontSee('"sku"');
        $parentShow->assertDontSee('inventory');
        $this->assertStringNotContainsString('stock', strtolower($parentShow->getContent()));

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.vendor-orders.show', $vendorOrder))
            ->assertOk()
            ->assertSeeText($vendorOrder->public_code)
            ->assertSeeText($parent->public_code)
            ->assertDontSee($sku)
            ->assertDontSee(__('Advance'), false)
            ->assertDontSee(__('Cancel order'), false);

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSeeText('COD pending')
            ->assertSeeText($vendorOrder->public_code)
            ->assertDontSeeText('Mark collected')
            ->assertDontSee($sku);

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText($admin->email)
            ->assertSeeText('Admin');

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSeeText('Global commission')
            ->assertSeeText('10%')
            ->assertSeeText('1000');

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.payments.index'), false)
            ->assertSee(route('admin.vendor-orders.index'), false);
    }

    /**
     * @return array{0: ParentOrder, 1: VendorOrder, 2: Payment, 3: string}
     */
    private function seedReadableOrderGraph(): array
    {
        $customer = User::factory()->create([
            'name' => 'Ops Customer',
            'email' => 'ops.customer@example.test',
        ]);
        $vendorUser = $this->createVendorUser();
        $store = $vendorUser->vendor->store;

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Pending,
                'currency_code' => 'SYP',
                'grand_total_amount_minor' => 12_000,
            ]);

        $sku = 'SECRET-SKU-ADM-C-999';
        OrderItem::factory()->for($vendorOrder)->create([
            'sku' => $sku,
            'product_name_en' => 'Test Product',
            'product_name_ar' => 'منتج تجريبي',
            'quantity' => 2,
            'currency_code' => 'SYP',
            'unit_price_amount_minor' => 6_000,
            'line_total_amount_minor' => 12_000,
            'store_name' => $store->name,
            'vendor_id' => $store->vendor_id,
            'store_id' => $store->id,
        ]);

        $payment = Payment::factory()->for($vendorOrder)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => 12_000,
            'currency_code' => 'SYP',
        ]);

        return [$parent->fresh(), $vendorOrder->fresh(), $payment->fresh(), $sku];
    }
}
