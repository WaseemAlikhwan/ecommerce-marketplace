<?php

namespace App\Storefront\Presentation;

use App\Models\Store;
use Illuminate\Support\Facades\Storage;

/**
 * Query-free Store page identity state.
 */
final class StorePagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Store $store, int $visibleProductCount): array
    {
        return [
            'slug' => (string) $store->slug,
            'name' => (string) $store->name,
            'description' => $store->description !== null ? (string) $store->description : null,
            'url' => route('storefront.store', $store->slug),
            'logo_url' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
            'banner_url' => $store->banner_path ? Storage::disk('public')->url($store->banner_path) : null,
            'initials' => $this->initials((string) $store->name),
            'visible_product_count' => max(0, $visibleProductCount),
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        );

        return mb_strtoupper(implode('', $letters) ?: mb_substr($name, 0, 1));
    }
}
