<?php

use App\Http\Controllers\Account\CustomerAddressController;
use App\Http\Controllers\Account\ParentOrderController;
use App\Http\Controllers\Account\ProductReviewController;
use App\Http\Controllers\Account\VendorApplicationController;
use App\Http\Controllers\Account\WishlistController;
use App\Http\Controllers\Admin\AttributeController as AdminAttributeController;
use App\Http\Controllers\Admin\AttributeValueController as AdminAttributeValueController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ParentOrderController as AdminParentOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VendorApplicationController as AdminVendorApplicationController;
use App\Http\Controllers\Admin\VendorOrderController as AdminVendorOrderController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Storefront\CartController as StorefrontCartController;
use App\Http\Controllers\Storefront\CartItemController as StorefrontCartItemController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\StoreController as StorefrontStoreController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Vendor\ProductImageController as VendorProductImageController;
use App\Http\Controllers\Vendor\ProductPublicationController as VendorProductPublicationController;
use App\Http\Controllers\Vendor\StoreController;
use App\Http\Controllers\Vendor\VendorOrderController;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/design-system', function () {
    if (! app()->environment(['local', 'testing']) && ! request()->user()?->isStaff()) {
        abort(404);
    }

    return view('design-system');
})->name('design-system');

Route::get('/search', SearchController::class)->name('storefront.search');
Route::get('/c/{slug}', StorefrontCategoryController::class)->name('storefront.category');
Route::get('/s/{slug}', StorefrontStoreController::class)->name('storefront.store');
Route::get('/p/{slug}', StorefrontProductController::class)->name('storefront.product');

Route::get('/cart', [StorefrontCartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [StorefrontCartItemController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{variant}', [StorefrontCartItemController::class, 'update'])
    ->whereNumber('variant')
    ->name('cart.items.update');
Route::delete('/cart/items/{variant}', [StorefrontCartItemController::class, 'destroy'])
    ->whereNumber('variant')
    ->name('cart.items.destroy');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'locale' => app()->getLocale(),
    ]);
})->name('health');

Route::post('/locale', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $addressCount = $user !== null && $user->isCustomer()
            ? CustomerAddress::query()->where('user_id', $user->id)->count()
            : 0;

        return view('dashboard', ['addressCount' => $addressCount]);
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/checkout', [StorefrontCheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [StorefrontCheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/coupon', [StorefrontCheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply');
    Route::delete('/checkout/coupon', [StorefrontCheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');

    Route::get('/account/orders', [ParentOrderController::class, 'index'])->name('account.orders');
    Route::get('/account/orders/{parentOrder}', [ParentOrderController::class, 'show'])->name('account.orders.show');
    Route::post('/account/orders/{parentOrder}/cancel', [ParentOrderController::class, 'cancel'])->name('account.orders.cancel');
    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/account/wishlist/{product}', [WishlistController::class, 'store'])
        ->whereNumber('product')
        ->name('account.wishlist.store');
    Route::delete('/account/wishlist/{wishlistItem}', [WishlistController::class, 'destroy'])
        ->whereNumber('wishlistItem')
        ->name('account.wishlist.destroy');
    Route::post('/account/reviews/{product}', [ProductReviewController::class, 'store'])
        ->whereNumber('product')
        ->name('account.reviews.store');
    Route::put('/account/reviews/{productReview}', [ProductReviewController::class, 'update'])
        ->whereNumber('productReview')
        ->name('account.reviews.update');
    Route::get('/account/addresses', [CustomerAddressController::class, 'index'])->name('account.addresses');
    Route::get('/account/addresses/create', [CustomerAddressController::class, 'create'])->name('account.addresses.create');
    Route::post('/account/addresses', [CustomerAddressController::class, 'store'])->name('account.addresses.store');
    Route::get('/account/addresses/{customerAddress}/edit', [CustomerAddressController::class, 'edit'])
        ->whereNumber('customerAddress')
        ->name('account.addresses.edit');
    Route::put('/account/addresses/{customerAddress}', [CustomerAddressController::class, 'update'])
        ->whereNumber('customerAddress')
        ->name('account.addresses.update');
    Route::delete('/account/addresses/{customerAddress}', [CustomerAddressController::class, 'destroy'])
        ->whereNumber('customerAddress')
        ->name('account.addresses.destroy');
    Route::post('/account/addresses/{customerAddress}/default', [CustomerAddressController::class, 'setDefault'])
        ->whereNumber('customerAddress')
        ->name('account.addresses.default');
    Route::get('/account/vendor-application', [VendorApplicationController::class, 'show'])->name('account.vendor-application');
    Route::post('/account/vendor-application', [VendorApplicationController::class, 'store'])->name('account.vendor-application.store');

    Route::middleware('staff')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/vendors', [AdminVendorApplicationController::class, 'index'])->name('vendors');
        Route::get('/vendor-applications/{vendor_application}', [AdminVendorApplicationController::class, 'show'])->name('vendor-applications.show');
        Route::post('/vendor-applications/{vendor_application}/approve', [AdminVendorApplicationController::class, 'approve'])->name('vendor-applications.approve');
        Route::post('/vendor-applications/{vendor_application}/reject', [AdminVendorApplicationController::class, 'reject'])->name('vendor-applications.reject');

        Route::get('/reviews', [AdminProductReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{productReview}', [AdminProductReviewController::class, 'show'])
            ->whereNumber('productReview')
            ->name('reviews.show');
        Route::post('/reviews/{productReview}/approve', [AdminProductReviewController::class, 'approve'])
            ->whereNumber('productReview')
            ->name('reviews.approve');
        Route::post('/reviews/{productReview}/reject', [AdminProductReviewController::class, 'reject'])
            ->whereNumber('productReview')
            ->name('reviews.reject');

        Route::get('/coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
        Route::get('/coupons/create', [AdminCouponController::class, 'create'])->name('coupons.create');
        Route::post('/coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
        Route::get('/coupons/{coupon}', [AdminCouponController::class, 'show'])
            ->whereNumber('coupon')
            ->name('coupons.show');
        Route::get('/coupons/{coupon}/edit', [AdminCouponController::class, 'edit'])
            ->whereNumber('coupon')
            ->name('coupons.edit');
        Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])
            ->whereNumber('coupon')
            ->name('coupons.update');
        Route::patch('/coupons/{coupon}/status', [AdminCouponController::class, 'updateStatus'])
            ->whereNumber('coupon')
            ->name('coupons.status');

        Route::get('/catalog', AdminCatalogController::class)->name('catalog');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/status', [AdminCategoryController::class, 'updateStatus'])->name('categories.status');

        Route::get('/brands', [AdminBrandController::class, 'index'])->name('brands.index');
        Route::get('/brands/create', [AdminBrandController::class, 'create'])->name('brands.create');
        Route::post('/brands', [AdminBrandController::class, 'store'])->name('brands.store');
        Route::get('/brands/{brand}/edit', [AdminBrandController::class, 'edit'])->name('brands.edit');
        Route::put('/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update');
        Route::patch('/brands/{brand}/status', [AdminBrandController::class, 'updateStatus'])->name('brands.status');

        Route::get('/attributes', [AdminAttributeController::class, 'index'])->name('attributes.index');
        Route::get('/attributes/create', [AdminAttributeController::class, 'create'])->name('attributes.create');
        Route::post('/attributes', [AdminAttributeController::class, 'store'])->name('attributes.store');
        Route::get('/attributes/{attribute}', [AdminAttributeController::class, 'show'])->name('attributes.show');
        Route::get('/attributes/{attribute}/edit', [AdminAttributeController::class, 'edit'])->name('attributes.edit');
        Route::put('/attributes/{attribute}', [AdminAttributeController::class, 'update'])->name('attributes.update');
        Route::patch('/attributes/{attribute}/status', [AdminAttributeController::class, 'updateStatus'])->name('attributes.status');

        Route::get('/attributes/{attribute}/values/create', [AdminAttributeValueController::class, 'create'])->name('attribute-values.create');
        Route::post('/attributes/{attribute}/values', [AdminAttributeValueController::class, 'store'])->name('attribute-values.store');
        Route::get('/attributes/{attribute}/values/{attribute_value}/edit', [AdminAttributeValueController::class, 'edit'])->name('attribute-values.edit');
        Route::put('/attributes/{attribute}/values/{attribute_value}', [AdminAttributeValueController::class, 'update'])->name('attribute-values.update');
        Route::patch('/attributes/{attribute}/values/{attribute_value}/status', [AdminAttributeValueController::class, 'updateStatus'])->name('attribute-values.status');

        Route::get('/orders', [AdminParentOrderController::class, 'index'])->name('orders');
        Route::get('/orders/{parentOrder}', [AdminParentOrderController::class, 'show'])
            ->whereNumber('parentOrder')
            ->name('orders.show');

        Route::get('/vendor-orders', [AdminVendorOrderController::class, 'index'])->name('vendor-orders.index');
        Route::get('/vendor-orders/{vendorOrder}', [AdminVendorOrderController::class, 'show'])
            ->whereNumber('vendorOrder')
            ->name('vendor-orders.show');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])
            ->whereNumber('payment')
            ->name('payments.show');
        Route::post('/payments/{payment}/collect', [AdminPaymentController::class, 'collect'])
            ->whereNumber('payment')
            ->name('payments.collect');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');

        Route::get('/settings', AdminSettingsController::class)->name('settings');
    });

    Route::middleware('vendor')->prefix('vendor')->name('vendor.')->group(function () {
        Route::get('/', VendorDashboardController::class)->name('dashboard');
        Route::get('/store', [StoreController::class, 'edit'])->name('store');
        Route::put('/store', [StoreController::class, 'update'])->name('store.update');
        Route::get('/products', [VendorProductController::class, 'index'])->name('products');
        Route::get('/products/create', [VendorProductController::class, 'create'])->name('products.create');
        Route::post('/products', [VendorProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [VendorProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [VendorProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/archive', [VendorProductController::class, 'archive'])->name('products.archive');
        Route::post('/products/{product}/publish', [VendorProductPublicationController::class, 'publish'])->name('products.publish');
        Route::post('/products/{product}/unpublish', [VendorProductPublicationController::class, 'unpublish'])->name('products.unpublish');
        Route::post('/products/{product}/images', [VendorProductImageController::class, 'store'])->name('products.images.store');
        Route::put('/products/{product}/images/reorder', [VendorProductImageController::class, 'reorder'])->name('products.images.reorder');
        Route::put('/products/{product}/images/{product_image}/primary', [VendorProductImageController::class, 'primary'])->name('products.images.primary');
        Route::put('/products/{product}/images/{product_image}/translations', [VendorProductImageController::class, 'translations'])->name('products.images.translations');
        Route::delete('/products/{product}/images/{product_image}', [VendorProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders');
        Route::get('/orders/{vendorOrder}', [VendorOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{vendorOrder}/advance', [VendorOrderController::class, 'advance'])->name('orders.advance');
        Route::post('/orders/{vendorOrder}/cancel', [VendorOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{vendorOrder}/collect-payment', [VendorOrderController::class, 'collectPayment'])
            ->name('orders.collect-payment');
    });
});

require __DIR__.'/auth.php';
