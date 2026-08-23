<?php

namespace App\Storefront\Presentation;

use App\Support\ModelFreePayload;

/**
 * Arranges already-presented public catalog data into Home page sections.
 */
final class HomePagePresenter
{
    /**
     * @param  list<array<string, mixed>>  $categories
     * @param  list<array<string, mixed>>  $products
     * @param  list<array<string, mixed>>  $stores
     * @return array{
     *     navigation: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     products: list<array<string, mixed>>,
     *     stores: list<array<string, mixed>>,
     *     hero_product: ?array<string, mixed>
     * }
     */
    public function present(array $categories, array $products, array $stores): array
    {
        ModelFreePayload::assert($categories, 'HomePagePresenter');
        ModelFreePayload::assert($products, 'HomePagePresenter');
        ModelFreePayload::assert($stores, 'HomePagePresenter');

        return [
            'navigation' => $categories,
            'categories' => array_slice($categories, 0, 6),
            'products' => array_slice($products, 0, 8),
            'stores' => array_slice($stores, 0, 6),
            'hero_product' => $products[0] ?? null,
        ];
    }
}
