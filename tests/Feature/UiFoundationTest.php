<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_home_is_arabic_rtl_by_default(): void
    {
        $this->withHeader('Accept-Language', '')
            ->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('Sham Market', locale: 'ar'), false);
    }

    public function test_storefront_home_switches_to_english_ltr(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Sham Market', false);
    }

    public function test_design_system_page_is_public(): void
    {
        $this->get('/design-system')
            ->assertOk()
            ->assertSee('primary', false)
            ->assertSee('surface', false);
    }

    public function test_empty_persisted_catalog_uses_public_route_contracts(): void
    {
        $this->get('/search')->assertOk();
        $this->get('/p/linen-throw')->assertNotFound();
        $this->get('/c/fashion')->assertNotFound();
        $this->get('/s/beit-sham')->assertNotFound();
        $this->get('/p/missing-item')->assertNotFound();
    }

    public function test_authentication_screens_render_marketplace_chrome(): void
    {
        $this->withHeader('Accept-Language', 'en');

        $this->get('/login')->assertOk()->assertSee('Welcome back', false);
        $this->get('/register')->assertOk()->assertSee('Create your account', false);
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password', false);
    }

    public function test_customer_shells_require_authentication(): void
    {
        $this->get('/account/orders')->assertRedirect('/login');
        $this->get('/account/wishlist')->assertRedirect('/login');
        $this->get('/account/addresses')->assertRedirect('/login');
    }

    public function test_authenticated_customer_can_open_account_shells(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/account/orders')->assertOk();
        $this->actingAs($user)->get('/account/wishlist')->assertOk();
        $this->actingAs($user)->get('/account/addresses')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_admin_and_vendor_shells_require_authorized_roles(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'ar']);
        $admin = User::factory()->admin()->create(['preferred_locale' => 'ar']);
        $vendor = $this->createVendorUser(['preferred_locale' => 'ar']);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/vendor')->assertForbidden();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/vendors')->assertOk();
        $this->actingAs($vendor)->get('/vendor')->assertOk();
        $this->actingAs($vendor)->get('/vendor/products')->assertOk();
    }
}
