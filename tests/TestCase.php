<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function createVendorUser(array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $vendor = Vendor::factory()->for($user)->create();
        Store::factory()->for($vendor)->create();
        $user->assignRole(Role::VENDOR);

        return $user->fresh(['vendor.store', 'roles']);
    }

    protected function makeProductImageUpload(
        string $format = 'jpeg',
        int $width = 400,
        int $height = 400,
        string $clientName = 'photo.bin',
        bool $noise = false,
    ): UploadedFile {
        $image = imagecreatetruecolor($width, $height);
        if ($format === 'png' || $format === 'webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $background = imagecolorallocatealpha($image, 30, 90, 160, 40);
        } else {
            $background = imagecolorallocate($image, 30, 90, 160);
        }
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        if ($noise) {
            for ($y = 0; $y < $height; $y += 3) {
                for ($x = 0; $x < $width; $x += 3) {
                    $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                    imagesetpixel($image, $x, $y, $color);
                }
            }
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'s5a-'.Str::ulid().'.'.$format;
        $ok = match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path, 0),
            'webp' => imagewebp($image, $path, 90),
            'gif' => imagegif($image, $path),
            'bmp' => imagebmp($image, $path),
            default => false,
        };
        imagedestroy($image);
        $this->assertTrue($ok !== false && is_file($path));

        $mime = match ($format) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };

        return new UploadedFile($path, $clientName, $mime, null, true);
    }

    /**
     * Ensure a product satisfies product-owned integrity so topology/economics
     * tests can forceFill Published without tripping S7A mutation guards.
     */
    protected function preparePublishedIntegrity(Product $product): Product
    {
        Storage::fake('public');

        if ($product->category_id === null) {
            $category = Category::factory()->create(['is_active' => true]);
            $product->forceFill(['category_id' => $category->id])->save();
        }

        $product->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'منتج تجريبي']);
        $product->translations()->updateOrCreate(['locale' => 'en'], [
            'name' => $product->translations()->where('locale', 'en')->value('name') ?: 'Test Product',
        ]);

        if ($product->images()->count() === 0) {
            app(ProductImageService::class)->upload($product->fresh(), $this->makeProductImageUpload());
        }

        return $product->fresh();
    }
}
