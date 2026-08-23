<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProductMigrationOrderTest extends TestCase
{
    public function test_product_migration_filename_sorts_after_store_taxonomy_and_currency(): void
    {
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values();

        $store = $migrations->first(fn (string $file): bool => str_contains($file, 'create_vendor_onboarding_tables'));
        $taxonomy = $migrations->first(fn (string $file): bool => str_contains($file, 'create_catalog_taxonomy_tables'));
        $currency = $migrations->first(fn (string $file): bool => str_contains($file, 'create_currencies_and_store_default_currency'));
        $products = $migrations->first(fn (string $file): bool => str_contains($file, 'create_products_tables'));
        $attributes = $migrations->first(fn (string $file): bool => str_contains($file, 'create_catalog_attribute_tables'));
        $variable = $migrations->first(fn (string $file): bool => str_contains($file, 'create_variable_product_domain'));

        $this->assertNotNull($store);
        $this->assertNotNull($taxonomy);
        $this->assertNotNull($currency);
        $this->assertNotNull($products);
        $this->assertNotNull($attributes);
        $this->assertNotNull($variable);

        $this->assertSame('2026_08_12_030000_create_products_tables.php', $products);
        $this->assertSame('2026_08_12_040000_create_catalog_attribute_tables.php', $attributes);
        $this->assertSame('2026_08_12_050000_create_variable_product_domain.php', $variable);
        $this->assertTrue($products > $store);
        $this->assertTrue($products > $taxonomy);
        $this->assertTrue($products > $currency);
        $this->assertTrue($attributes > $products);
        $this->assertTrue($variable > $attributes);

        $productIndex = $migrations->search($products);
        $this->assertGreaterThan($migrations->search($store), $productIndex);
        $this->assertGreaterThan($migrations->search($taxonomy), $productIndex);
        $this->assertGreaterThan($migrations->search($currency), $productIndex);
        $this->assertGreaterThan($productIndex, $migrations->search($attributes));
        $this->assertGreaterThan($migrations->search($attributes), $migrations->search($variable));
    }
}
