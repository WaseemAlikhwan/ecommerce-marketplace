<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\ShippingCalculator;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Payments\CodPaymentGateway;
use App\Shipping\FlatPerVendorShippingCalculator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ShippingCalculator::class, FlatPerVendorShippingCalculator::class);
        $this->app->bind(PaymentGateway::class, CodPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // P0-7 / ADR-020: minimum 8 characters, confirmed at call sites; no complexity class.
        Password::defaults(static fn () => Password::min(8));

        Route::bind('product', function (string $value): Product {
            $query = Product::query();

            if (request()->routeIs('vendor.products.*')) {
                $query->withTrashed();
            }

            return $query->whereKey($value)->firstOrFail();
        });

        Route::bind('product_image', function (string $value, \Illuminate\Routing\Route $route): ProductImage {
            $query = ProductImage::query()->whereKey($value);

            $product = $route->parameter('product');
            if ($product instanceof Product) {
                $query->where('product_id', $product->id)->where('store_id', $product->store_id);
            } elseif (is_numeric($product)) {
                $query->where('product_id', (int) $product);
            }

            return $query->firstOrFail();
        });

        Route::bind('attribute_value', function (string $value, \Illuminate\Routing\Route $route): AttributeValue {
            $query = AttributeValue::query()->whereKey($value);

            $attribute = $route->parameter('attribute');

            if ($attribute instanceof Attribute) {
                $query->where('attribute_id', $attribute->id);
            } elseif (is_numeric($attribute)) {
                $query->where('attribute_id', (int) $attribute);
            }

            return $query->firstOrFail();
        });
    }
}
