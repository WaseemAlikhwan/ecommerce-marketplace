<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_arabic_without_cookie_or_accept_language(): void
    {
        $response = $this->withHeader('Accept-Language', '')
            ->get('/health');

        $response->assertOk()
            ->assertJsonPath('locale', 'ar');
    }

    public function test_accept_language_english_is_used_on_first_visit(): void
    {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/health');

        $response->assertOk()
            ->assertJsonPath('locale', 'en');
    }

    public function test_authenticated_user_can_switch_locale_and_persist_preference(): void
    {
        $user = User::factory()->create(['preferred_locale' => 'ar']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/dashboard');

        $this->assertSame('en', $user->fresh()->preferred_locale);

        $this->actingAs($user)
            ->get('/health')
            ->assertJsonPath('locale', 'en');
    }

    public function test_locale_cookie_name_constant(): void
    {
        $this->assertSame('locale', Locale::COOKIE);
        $this->assertSame('rtl', Locale::direction('ar'));
        $this->assertSame('ltr', Locale::direction('en'));
    }

    public function test_guest_can_switch_locale_and_stay_on_the_same_page(): void
    {
        $this->from('/search')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/search');

        $this->withCookie(Locale::COOKIE, 'en')
            ->withHeader('Accept-Language', '')
            ->get('/search')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);
    }

    public function test_super_admin_role_can_be_assigned(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SUPER_ADMIN);

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertTrue($user->isCustomer());
    }
}
