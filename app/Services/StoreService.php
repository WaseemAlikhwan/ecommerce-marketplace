<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Store $store, array $attributes, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Store
    {
        if ($logo !== null) {
            $this->replaceImage($store, 'logo_path', $logo, 'stores/logos');
        }

        if ($banner !== null) {
            $this->replaceImage($store, 'banner_path', $banner, 'stores/banners');
        }

        if (isset($attributes['name']) && $attributes['name'] !== $store->name) {
            $attributes['slug'] = $this->uniqueSlug((string) $attributes['name'], $store->id);
        }

        $store->fill($attributes)->save();

        return $store->refresh();
    }

    private function replaceImage(Store $store, string $column, UploadedFile $file, string $directory): void
    {
        $previous = $store->{$column};

        $store->{$column} = $file->store($directory, 'public');

        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }
    }

    private function uniqueSlug(string $name, int $ignoreId): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'store';
        $slug = $base;
        $i = 1;

        while (Store::query()->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
