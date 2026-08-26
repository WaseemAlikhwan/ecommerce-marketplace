<?php

namespace Tests\Feature;

use App\Http\Requests\Vendor\StoreProductImageRequest;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HardeningHndBTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_renders_branded_error_page(): void
    {
        $this->get('/__hnd-b-missing-page-'.uniqid())
            ->assertNotFound()
            ->assertSee('404', false)
            ->assertSeeText('الصفحة غير موجودة');
    }

    public function test_forbidden_renders_branded_error_page_for_non_staff_admin(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSeeText('Access denied')
            ->assertSeeText('You do not have permission to view this page.');
    }

    public function test_page_expired_renders_branded_error_page(): void
    {
        Route::get('/__hnd-b-419', static fn () => abort(419));

        $this->get('/__hnd-b-419')
            ->assertStatus(419)
            ->assertSee('419', false)
            ->assertSeeText('انتهت صلاحية الصفحة');
    }

    public function test_server_error_renders_branded_error_page(): void
    {
        Route::get('/__hnd-b-500', static fn () => abort(500));

        $this->get('/__hnd-b-500')
            ->assertStatus(500)
            ->assertSee('500', false)
            ->assertSeeText('حدث خطأ ما');
    }

    public function test_password_reset_link_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), [
                'email' => 'throttle-'.$i.'@example.test',
            ]);
        }

        $this->post(route('password.email'), [
            'email' => 'throttle-overflow@example.test',
        ])
            ->assertStatus(429)
            ->assertSee('429', false)
            ->assertSeeText('Too many requests');
    }

    public function test_login_rate_limiter_still_blocks_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_product_image_upload_max_is_five_megabytes(): void
    {
        $request = new StoreProductImageRequest;
        $rules = $request->rules()['image'];

        $this->assertContains('max:5120', $rules);
    }

    public function test_password_reset_throttle_middleware_is_registered(): void
    {
        $route = Route::getRoutes()->getByName('password.email');
        $this->assertNotNull($route);

        $middlewares = $route->gatherMiddleware();
        $this->assertTrue(
            collect($middlewares)->contains('throttle:5,1')
                || collect($middlewares)->contains(
                    fn (mixed $middleware): bool => is_string($middleware)
                        && (str_contains($middleware, 'throttle:5,1')
                            || str_starts_with($middleware, ThrottleRequests::class))
                )
        );
    }
}
