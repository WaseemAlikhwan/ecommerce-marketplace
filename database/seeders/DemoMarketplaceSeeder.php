<?php

namespace Database\Seeders;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\AttributeValue;
use App\Models\AttributeValueTranslation;
use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Services\VendorOrderLifecycleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Local/staging marketplace demo data for the full V1 walkthrough.
 *
 * Not called from DatabaseSeeder. Run via: php artisan demo:seed
 */
class DemoMarketplaceSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public const CUSTOMER_EMAIL = 'customer@demo.test';

    public const VENDOR_SYP_EMAIL = 'vendor.syp@demo.test';

    public const VENDOR_USD_EMAIL = 'vendor.usd@demo.test';

    public const VENDOR_SYP2_EMAIL = 'vendor.syp2@demo.test';

    public const PLATFORM_COUPON = 'SAVE10';

    public const VENDOR_COUPON = 'SHOPSYP10';

    private const MARKER_DELIVERED = 'demo-marketplace:delivered';

    private const MARKER_PENDING_MULTI = 'demo-marketplace:pending-multi';

    private const MARKER_CONFIRMED = 'demo-marketplace:confirmed';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing DemoMarketplaceSeeder in production.');

            return;
        }

        $this->ensureFoundation();

        $customer = $this->upsertUser(self::CUSTOMER_EMAIL, 'Demo Customer', '+963911000001');
        $customer->assignRole(Role::CUSTOMER);

        $vendorSyp = $this->upsertVendorUser(
            self::VENDOR_SYP_EMAIL,
            'Demo Vendor SYP',
            '+963911000002',
            'Demo Silk House',
            'demo-silk-house',
            'SYP',
        );
        $vendorUsd = $this->upsertVendorUser(
            self::VENDOR_USD_EMAIL,
            'Demo Vendor USD',
            '+963911000003',
            'Demo Olive Mart',
            'demo-olive-mart',
            'USD',
        );
        $vendorSyp2 = $this->upsertVendorUser(
            self::VENDOR_SYP2_EMAIL,
            'Demo Vendor SYP 2',
            '+963911000004',
            'Demo Cedar Crafts',
            'demo-cedar-crafts',
            'SYP',
        );

        $category = $this->upsertCategory('demo-home', 'Home', 'منزل', 10);
        $fashion = $this->upsertCategory('demo-fashion', 'Fashion', 'أزياء', 20);
        $brand = $this->upsertBrand('demo-nur', 'Nur Home', 'نور');
        $brandFashion = $this->upsertBrand('demo-silk', 'Silk Lane', 'حرير');

        $simple = $this->upsertSimpleProduct(
            $vendorSyp->vendor->store,
            'demo-linen-scarf',
            'Demo Linen Scarf',
            'وشاح كتان تجريبي',
            $category,
            $brand,
            'SYP',
            '2500',
            40,
            'DEMO-LINEN-SCARF',
        );

        $variable = $this->upsertVariableProduct(
            $vendorUsd->vendor->store,
            'demo-cotton-tee',
            'Demo Cotton Tee',
            'تيشيرت قطن تجريبي',
            $fashion,
            $brandFashion,
            'USD',
        );

        $extraSimple = $this->upsertSimpleProduct(
            $vendorSyp2->vendor->store,
            'demo-cedar-bowl',
            'Demo Cedar Bowl',
            'وعاء أرز تجريبي',
            $category,
            $brand,
            'SYP',
            '1800',
            25,
            'DEMO-CEDAR-BOWL',
        );

        $this->upsertCoupon(
            self::PLATFORM_COUPON,
            CouponScope::Platform,
            null,
            CouponType::Percent,
            10,
            'SYP',
        );
        $this->upsertCoupon(
            self::VENDOR_COUPON,
            CouponScope::Vendor,
            $vendorSyp->vendor->id,
            CouponType::Percent,
            10,
            'SYP',
        );

        $address = $this->upsertDefaultAddress($customer);

        $this->seedDeliveredOrder($customer, $simple, $vendorSyp);
        $this->seedPendingMultiVendorOrder($customer, $address, $simple, $extraSimple);
        $this->seedConfirmedVendorOrder($customer, $address, $variable, $vendorUsd);

        $this->command?->info('Demo marketplace seeded.');
        $this->command?->table(
            ['Role', 'Email', 'Password'],
            [
                ['Customer', self::CUSTOMER_EMAIL, self::DEMO_PASSWORD],
                ['Vendor (SYP)', self::VENDOR_SYP_EMAIL, self::DEMO_PASSWORD],
                ['Vendor (USD)', self::VENDOR_USD_EMAIL, self::DEMO_PASSWORD],
                ['Vendor (SYP 2)', self::VENDOR_SYP2_EMAIL, self::DEMO_PASSWORD],
            ],
        );
        $this->command?->info('Coupons: '.self::PLATFORM_COUPON.' (platform 10% SYP), '.self::VENDOR_COUPON.' (vendor SYP 10%).');
    }

    private function ensureFoundation(): void
    {
        $this->call([
            RoleSeeder::class,
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
            CommissionSettingSeeder::class,
        ]);
    }

    private function upsertUser(string $email, string $name, string $phone): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'phone' => $phone,
            'preferred_locale' => 'ar',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if (! $user->exists || ! filled($user->password)) {
            $user->password = Hash::make(self::DEMO_PASSWORD);
        }

        $user->save();

        return $user->fresh();
    }

    private function upsertVendorUser(
        string $email,
        string $name,
        string $phone,
        string $storeName,
        string $storeSlug,
        string $currencyCode,
    ): User {
        $user = $this->upsertUser($email, $name, $phone);
        $user->assignRole(Role::CUSTOMER);
        $user->assignRole(Role::VENDOR);

        $vendor = Vendor::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => VendorStatus::Approved],
        );

        if ($vendor->status !== VendorStatus::Approved) {
            $vendor->forceFill(['status' => VendorStatus::Approved])->save();
        }

        $store = Store::query()->firstOrNew(['vendor_id' => $vendor->id]);
        $store->fill([
            'name' => $storeName,
            'slug' => $storeSlug,
            'description' => 'Demo store for local walkthroughs.',
            'contact_email' => $email,
            'contact_phone' => $phone,
            'status' => StoreStatus::Active,
            'default_currency_code' => $currencyCode,
            'flat_shipping_amount_minor' => $currencyCode === 'USD' ? 500 : 2000,
        ]);
        $store->save();

        return $user->fresh(['vendor.store', 'roles']);
    }

    private function upsertCategory(string $slug, string $en, string $ar, int $position): Category
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['position' => $position, 'is_active' => true, 'parent_id' => null],
        );

        $category->forceFill(['is_active' => true, 'position' => $position])->save();

        CategoryTranslation::query()->updateOrCreate(
            ['category_id' => $category->id, 'locale' => 'en'],
            ['name' => $en],
        );
        CategoryTranslation::query()->updateOrCreate(
            ['category_id' => $category->id, 'locale' => 'ar'],
            ['name' => $ar],
        );

        return $category->fresh('translations');
    }

    private function upsertBrand(string $slug, string $en, string $ar): Brand
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => $slug],
            ['is_active' => true],
        );
        $brand->forceFill(['is_active' => true])->save();

        BrandTranslation::query()->updateOrCreate(
            ['brand_id' => $brand->id, 'locale' => 'en'],
            ['name' => $en],
        );
        BrandTranslation::query()->updateOrCreate(
            ['brand_id' => $brand->id, 'locale' => 'ar'],
            ['name' => $ar],
        );

        return $brand->fresh('translations');
    }

    private function upsertSimpleProduct(
        Store $store,
        string $slug,
        string $en,
        string $ar,
        Category $category,
        Brand $brand,
        string $currency,
        string $price,
        int $quantity,
        string $sku,
    ): Product {
        $existing = Product::query()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->first();

        if ($existing === null) {
            Auth::login($store->vendor->user);
            $product = app(ProductService::class)->createSimpleDraft($store, [
                'type' => 'simple',
                'slug' => $slug,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_code' => $currency,
                'sku' => $sku,
                'price' => $price,
                'quantity' => $quantity,
                'translations' => [
                    'en' => ['name' => $en, 'short_description' => 'Demo simple product'],
                    'ar' => ['name' => $ar, 'short_description' => 'منتج تجريبي بسيط'],
                ],
            ]);
            Auth::logout();
        } else {
            $product = $existing;
        }

        $this->ensurePlaceholderImage($product);
        $this->ensureVariantStock($product, $quantity);
        $this->publish($product);

        return $product->fresh(['defaultVariant', 'store.vendor.user']);
    }

    private function upsertVariableProduct(
        Store $store,
        string $slug,
        string $en,
        string $ar,
        Category $category,
        Brand $brand,
        string $currency,
    ): Product {
        $existing = Product::query()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->first();

        if ($existing === null) {
            [$attribute, $red, $blue, $green] = $this->upsertColorAttribute();

            Auth::login($store->vendor->user);
            $product = app(ProductService::class)->createVariableDraft($store, [
                'type' => 'variable',
                'slug' => $slug,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_code' => $currency,
                'translations' => [
                    'en' => ['name' => $en, 'short_description' => 'Demo variable product'],
                    'ar' => ['name' => $ar, 'short_description' => 'منتج متغير تجريبي'],
                ],
                'attributes' => [
                    [
                        'attribute_id' => $attribute->id,
                        'value_ids' => [$red->id, $blue->id, $green->id],
                    ],
                ],
                'variants' => [
                    [
                        'value_ids' => [$red->id],
                        'sku' => 'DEMO-TEE-RED',
                        'price' => '18.00',
                        'quantity' => 15,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$blue->id],
                        'sku' => 'DEMO-TEE-BLUE',
                        'price' => '19.50',
                        'quantity' => 12,
                        'is_default' => false,
                    ],
                    [
                        'value_ids' => [$green->id],
                        'sku' => 'DEMO-TEE-GREEN',
                        'price' => '17.25',
                        'quantity' => 10,
                        'is_default' => false,
                    ],
                ],
            ]);
            Auth::logout();
        } else {
            $product = $existing;
        }

        $this->ensurePlaceholderImage($product);
        $this->ensureVariantStock($product, 10);
        $this->publish($product);

        return $product->fresh(['defaultVariant', 'variants', 'store.vendor.user']);
    }

    /**
     * @return array{0: Attribute, 1: AttributeValue, 2: AttributeValue, 3: AttributeValue}
     */
    private function upsertColorAttribute(): array
    {
        $attribute = Attribute::query()->firstOrCreate(
            ['code' => 'demo-color'],
            ['position' => 0, 'is_active' => true],
        );
        AttributeTranslation::query()->updateOrCreate(
            ['attribute_id' => $attribute->id, 'locale' => 'en'],
            ['name' => 'Color'],
        );
        AttributeTranslation::query()->updateOrCreate(
            ['attribute_id' => $attribute->id, 'locale' => 'ar'],
            ['name' => 'اللون'],
        );

        $red = $this->upsertAttributeValue($attribute, 'demo-red', 'Red', 'أحمر', 0);
        $blue = $this->upsertAttributeValue($attribute, 'demo-blue', 'Blue', 'أزرق', 1);
        $green = $this->upsertAttributeValue($attribute, 'demo-green', 'Green', 'أخضر', 2);

        return [$attribute, $red, $blue, $green];
    }

    private function upsertAttributeValue(
        Attribute $attribute,
        string $code,
        string $en,
        string $ar,
        int $position,
    ): AttributeValue {
        $value = AttributeValue::query()->firstOrCreate(
            ['attribute_id' => $attribute->id, 'code' => $code],
            ['position' => $position, 'is_active' => true],
        );
        AttributeValueTranslation::query()->updateOrCreate(
            ['attribute_value_id' => $value->id, 'locale' => 'en'],
            ['name' => $en],
        );
        AttributeValueTranslation::query()->updateOrCreate(
            ['attribute_value_id' => $value->id, 'locale' => 'ar'],
            ['name' => $ar],
        );

        return $value;
    }

    private function ensurePlaceholderImage(Product $product): void
    {
        if ($product->images()->exists() && $product->primary_image_id !== null) {
            return;
        }

        $relative = 'products/'.$product->id.'/demo-'.Str::lower((string) Str::ulid()).'.jpg';
        $absolute = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        $image = imagecreatetruecolor(640, 480);
        $bg = imagecolorallocate($image, 210, 180, 140);
        imagefilledrectangle($image, 0, 0, 640, 480, $bg);
        imagejpeg($image, $absolute, 85);
        imagedestroy($image);

        $row = ProductImage::query()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'path' => $relative,
            'mime_type' => 'image/jpeg',
            'size_bytes' => (int) filesize($absolute),
            'width' => 640,
            'height' => 480,
            'position' => 0,
        ]);

        $product->forceFill(['primary_image_id' => $row->id])->save();
    }

    private function ensureVariantStock(Product $product, int $minQuantity): void
    {
        ProductVariant::query()
            ->where('product_id', $product->id)
            ->get()
            ->each(function (ProductVariant $variant) use ($minQuantity): void {
                if ((int) $variant->quantity < $minQuantity) {
                    $variant->forceFill(['quantity' => $minQuantity])->save();
                }
            });
    }

    private function publish(Product $product): void
    {
        if ($product->status === ProductStatus::Published) {
            return;
        }

        app(ProductPublicationService::class)->publish($product->fresh());
    }

    private function upsertCoupon(
        string $code,
        CouponScope $scope,
        ?int $vendorId,
        CouponType $type,
        int $value,
        string $currency,
    ): Coupon {
        return Coupon::query()->updateOrCreate(
            ['code' => $code],
            [
                'scope' => $scope,
                'vendor_id' => $scope === CouponScope::Vendor ? $vendorId : null,
                'type' => $type,
                'value' => $value,
                'currency_code' => $currency,
                'starts_at' => null,
                'ends_at' => null,
                'min_eligible_amount_minor' => 0,
                'max_discount_amount_minor' => null,
                'global_usage_limit' => null,
                'per_user_usage_limit' => null,
                'is_active' => true,
            ],
        );
    }

    private function upsertDefaultAddress(User $customer): CustomerAddress
    {
        $existing = CustomerAddress::query()
            ->where('user_id', $customer->id)
            ->where('is_default', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return CustomerAddress::factory()->for($customer)->default()->create([
            'label' => 'Demo home',
            'recipient_name' => $customer->name,
            'phone' => $customer->phone,
        ]);
    }

    private function seedDeliveredOrder(User $customer, Product $product, User $vendorUser): void
    {
        $exists = ParentOrder::query()
            ->where('user_id', $customer->id)
            ->where('shipping_notes', self::MARKER_DELIVERED)
            ->exists();

        if ($exists) {
            return;
        }

        $store = $vendorUser->vendor->store;
        $variant = $product->defaultVariant;
        $unit = 2500;
        $qty = 1;
        $shipping = 2000;
        $subtotal = $unit * $qty;
        $commissionBps = 1000;
        $commissionAmount = intdiv($subtotal * $commissionBps, 10_000);

        $parent = ParentOrder::factory()->create([
            'user_id' => $customer->id,
            'status' => ParentOrderStatus::Placed,
            'shipping_notes' => self::MARKER_DELIVERED,
            'shipping_recipient_name' => $customer->name,
            'shipping_phone' => $customer->phone,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Delivered,
                'items_subtotal_amount_minor' => $subtotal,
                'shipping_amount_minor' => $shipping,
                'discount_amount_minor' => 0,
                'grand_total_amount_minor' => $subtotal + $shipping,
                'commission_rate_bps' => $commissionBps,
                'commission_base_amount_minor' => $subtotal,
                'commission_amount_minor' => $commissionAmount,
                'commission_recognized_at' => now(),
                'currency_code' => 'SYP',
            ]);

        OrderItem::factory()->for($vendorOrder)->create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'store_id' => $store->id,
            'vendor_id' => $store->vendor_id,
            'quantity' => $qty,
            'unit_price_amount_minor' => $unit,
            'line_total_amount_minor' => $subtotal,
            'currency_code' => 'SYP',
            'product_name_ar' => 'وشاح كتان تجريبي',
            'product_name_en' => 'Demo Linen Scarf',
            'sku' => 'DEMO-LINEN-SCARF',
            'store_name' => $store->name,
        ]);

        Payment::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'currency_code' => 'SYP',
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'collected_at' => null,
        ]);
    }

    private function seedPendingMultiVendorOrder(
        User $customer,
        CustomerAddress $address,
        Product $productA,
        Product $productB,
    ): void {
        $exists = ParentOrder::query()
            ->where('user_id', $customer->id)
            ->where('shipping_notes', self::MARKER_PENDING_MULTI)
            ->exists();

        if ($exists) {
            return;
        }

        $variantA = $productA->fresh('defaultVariant')->defaultVariant;
        $variantB = $productB->fresh('defaultVariant')->defaultVariant;
        if ($variantA === null || $variantB === null) {
            return;
        }

        $carts = app(CartService::class);
        $carts->clear($customer);
        $carts->add($customer, $variantA->id, 1);
        $carts->add($customer, $variantB->id, 1);

        $result = app(CheckoutService::class)->placeOrder($customer, $address);
        $result->parentOrder->forceFill([
            'shipping_notes' => self::MARKER_PENDING_MULTI,
        ])->save();
    }

    private function seedConfirmedVendorOrder(
        User $customer,
        CustomerAddress $address,
        Product $product,
        User $vendorUser,
    ): void {
        $exists = ParentOrder::query()
            ->where('user_id', $customer->id)
            ->where('shipping_notes', self::MARKER_CONFIRMED)
            ->exists();

        if ($exists) {
            return;
        }

        $variant = $product->fresh('defaultVariant')->defaultVariant;
        if ($variant === null) {
            return;
        }

        $carts = app(CartService::class);
        $carts->clear($customer);
        $carts->add($customer, $variant->id, 1);

        $result = app(CheckoutService::class)->placeOrder($customer, $address);
        $parent = $result->parentOrder;
        $parent->forceFill(['shipping_notes' => self::MARKER_CONFIRMED])->save();

        $vendorOrder = $parent->vendorOrders()
            ->where('vendor_id', $vendorUser->vendor->id)
            ->first();

        if ($vendorOrder === null) {
            return;
        }

        app(VendorOrderLifecycleService::class)->confirm($vendorUser, $vendorOrder);
    }
}
