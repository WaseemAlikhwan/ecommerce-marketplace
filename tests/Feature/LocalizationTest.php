<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function publicUiRoutes(): array
    {
        return [
            '/',
            '/login',
            '/register',
            '/forgot-password',
            '/search',
            '/design-system',
        ];
    }

    public function test_arabic_html_document_uses_rtl_and_arabic_copy(): void
    {
        $this->withHeader('Accept-Language', '')
            ->get('/')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('تصفّح المنتجات', false)
            ->assertSee('اكتشف منتجات من متاجر مستقلة', false)
            ->assertSee('تسجيل الدخول', false)
            ->assertSee('سوق سوري للمنتجات اليومية', false)
            ->assertSee('السلة', false)
            ->assertDontSee('المفضلة', false)
            ->assertDontSee('Browse products', false)
            ->assertDontSee('Discover products from independent stores.', false);
    }

    public function test_english_html_document_uses_ltr_and_english_copy(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Browse products', false)
            ->assertSee('A Syrian marketplace for everyday goods', false)
            ->assertSee('Cart', false)
            ->assertDontSee('Wishlist', false)
            ->assertDontSee('تصفّح المنتجات', false)
            ->assertDontSee('السلة', false)
            ->assertDontSee('ب ش', false);
    }

    public function test_catalog_and_auth_pages_follow_locale_direction(): void
    {
        $this->withHeader('Accept-Language', '')
            ->get('/search')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('لا توجد منتجات مطابقة', false);

        $this->withHeader('Accept-Language', 'en')
            ->get('/search')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('No matching products', false)
            ->assertDontSee('Add to cart', false);

        $this->withHeader('Accept-Language', '')
            ->get('/login')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('تسجيل الدخول', false);

        $this->withHeader('Accept-Language', 'en')
            ->get('/register')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Create your account', false);
    }

    public function test_locale_switch_preserves_the_current_route(): void
    {
        $this->from('/p/linen-throw')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/p/linen-throw');

        $this->from('/login')
            ->post('/locale', ['locale' => 'ar'])
            ->assertRedirect('/login');
    }

    public function test_guest_locale_cookie_wins_over_accept_language(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/')
            ->assertCookie(Locale::COOKIE, 'en');

        $this->withCookie(Locale::COOKIE, 'en')
            ->withHeader('Accept-Language', 'ar')
            ->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Browse products', false);
    }

    public function test_authenticated_user_locale_persists_on_profile_and_cookie_path(): void
    {
        $user = User::factory()->create(['preferred_locale' => 'ar']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/dashboard');

        $this->assertSame('en', $user->fresh()->preferred_locale);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Welcome,', false);

        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Operations desk', false);

        $vendor = $this->createVendorUser(['preferred_locale' => 'en']);
        $this->actingAs($vendor)
            ->get('/vendor')
            ->assertOk()
            ->assertSee('Seller workspace', false);
    }

    public function test_arabic_authentication_errors_are_localized(): void
    {
        $this->withCookie(Locale::COOKIE, 'ar')
            ->withHeader('Accept-Language', 'ar')
            ->from('/login')
            ->post('/login', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            collect(session('errors')->get('email'))->implode(' '),
        );

        $this->withCookie(Locale::COOKIE, 'ar')
            ->withHeader('Accept-Language', 'ar')
            ->from('/register')
            ->post('/register', [])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'password']);

        $this->assertStringContainsString(
            'الاسم مطلوب',
            collect(session('errors')->get('name'))->implode(' '),
        );
    }

    public function test_english_authentication_errors_stay_in_english(): void
    {
        $this->withCookie(Locale::COOKIE, 'en')
            ->withHeader('Accept-Language', 'en')
            ->from('/login')
            ->post('/login', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'These credentials do not match our records.',
            collect(session('errors')->get('email'))->implode(' '),
        );
    }

    public function test_major_ui_routes_render_in_both_locales(): void
    {
        foreach ($this->publicUiRoutes() as $uri) {
            $this->withHeader('Accept-Language', '')
                ->get($uri)
                ->assertOk()
                ->assertSee('lang="ar"', false)
                ->assertSee('dir="rtl"', false);

            $this->withHeader('Accept-Language', 'en')
                ->get($uri)
                ->assertOk()
                ->assertSee('lang="en"', false)
                ->assertSee('dir="ltr"', false);
        }

        $user = User::factory()->create(['preferred_locale' => 'en']);

        foreach (['/dashboard', '/account/orders', '/account/wishlist', '/account/addresses', '/profile', '/account/vendor-application'] as $uri) {
            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertSee('lang="en"', false)
                ->assertSee('dir="ltr"', false);
        }

        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);

        $vendor = $this->createVendorUser(['preferred_locale' => 'en']);
        $this->actingAs($vendor)->get('/vendor')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);

        $user->forceFill(['preferred_locale' => 'ar'])->save();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);

        $admin->forceFill(['preferred_locale' => 'ar'])->save();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);

        $vendor->forceFill(['preferred_locale' => 'ar'])->save();
        $this->actingAs($vendor)->get('/vendor')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
    }

    public function test_mobile_menu_exposes_language_switcher(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('aria-label="Language"', false)
            ->assertSee('name="locale"', false)
            ->assertSee('value="ar"', false)
            ->assertSee('value="en"', false);
    }

    public function test_translation_files_cover_storefront_json_keys(): void
    {
        $en = json_decode(File::get(lang_path('en.json')), true);
        $ar = json_decode(File::get(lang_path('ar.json')), true);

        $this->assertIsArray($en);
        $this->assertIsArray($ar);
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ar));
        $this->assertSame('No matching products', $en['No matching products']);
        $this->assertSame('لا توجد منتجات مطابقة', $ar['No matching products']);
        $this->assertSame(
            'The search text format was not valid and was ignored.',
            $en['The search text format was not valid and was ignored.'],
        );
        $this->assertSame('Browse products', $en['Browse products']);
        $this->assertSame('تصفّح المنتجات', $ar['Browse products']);
    }

    public function test_s8c_storefront_literal_translation_keys_exist_in_both_languages(): void
    {
        $en = json_decode(File::get(lang_path('en.json')), true);
        $ar = json_decode(File::get(lang_path('ar.json')), true);
        $files = [
            ...File::allFiles(resource_path('views/storefront')),
            ...File::allFiles(resource_path('views/components/commerce')),
            resource_path('views/components/ui/pagination.blade.php'),
            resource_path('views/layouts/storefront.blade.php'),
        ];

        $keys = [];
        foreach ($files as $file) {
            $contents = File::get((string) $file);
            preg_match_all("/__\(\s*'([^']+)'/", $contents, $singleQuoted);
            preg_match_all('/__\(\s*"([^"]+)"/', $contents, $doubleQuoted);
            $keys = [...$keys, ...$singleQuoted[1], ...$doubleQuoted[1]];
        }

        $missing = [];
        foreach (array_values(array_unique($keys)) as $key) {
            if (! array_key_exists($key, $en)) {
                $missing[] = "en:{$key}";
            }
            if (! array_key_exists($key, $ar)) {
                $missing[] = "ar:{$key}";
            }
        }

        $this->assertSame([], $missing, 'Missing literal storefront translations: '.implode(', ', $missing));
    }
}
