<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Storefront\CatalogAvailability;
use App\Storefront\CatalogCriteria;
use App\Storefront\CatalogCriteriaIssueCode;
use App\Storefront\CatalogCriteriaResult;
use App\Storefront\CatalogSort;
use Illuminate\Support\Collection;
use LogicException;
use PHPUnit\Framework\TestCase;

class CatalogCriteriaTest extends TestCase
{
    public function test_defaults_and_unknown_params_ignored(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'foo' => 'bar',
            'q' => '  phone  ',
        ]);

        $this->assertSame('phone', $criteria->q);
        $this->assertSame(CatalogSort::Newest, $criteria->sort);
        $this->assertSame(CatalogAvailability::Any, $criteria->availability);
        $this->assertSame([], $criteria->issues);
    }

    public function test_keyword_truncation_and_sort_validation(): void
    {
        $long = str_repeat('a', 90);
        $criteria = CatalogCriteria::fromInput([
            'q' => $long,
            'sort' => 'popular',
            'availability' => 'maybe',
        ]);

        $this->assertSame(80, mb_strlen((string) $criteria->q));
        $this->assertSame(CatalogSort::Newest, $criteria->sort);
        $this->assertSame(CatalogAvailability::Any, $criteria->availability);
        $this->assertContains(CatalogCriteriaIssueCode::Q_TRUNCATED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::SORT_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::AVAILABILITY_INVALID, $criteria->issues);
    }

    public function test_price_requires_currency_and_attribute_limits(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'min_price' => '10',
            'max_price' => '5',
            'sort' => 'price_asc',
            'attrs' => [
                'color' => ['red', 'red', 'blue', 'green', 'yellow', 'black', 'white', 'pink', 'orange', 'purple'],
                'size' => ['s'],
                'material' => ['cotton'],
                'fit' => ['slim'],
            ],
        ]);

        $this->assertNull($criteria->minPriceInput);
        $this->assertNull($criteria->maxPriceInput);
        $this->assertSame(CatalogSort::Newest, $criteria->sort);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_SORT_CURRENCY_REQUIRED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTES_LIMIT_EXCEEDED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTE_VALUES_LIMIT_EXCEEDED, $criteria->issues);
        $this->assertCount(3, $criteria->attributes);
        $this->assertCount(7, $criteria->attributes['color']);
    }

    public function test_attribute_value_dedupe_with_currency(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'currency' => 'syp',
            'min_price' => '1.00',
            'max_price' => '2.00',
            'sort' => 'price_desc',
            'attrs' => [
                'color' => ['Red', 'red', 'BLUE'],
            ],
        ]);

        $this->assertSame('SYP', $criteria->currencyCode);
        $this->assertSame(['red', 'blue'], $criteria->attributes['color']);
        $this->assertSame(CatalogSort::PriceDesc, $criteria->sort);
        $this->assertSame([], $criteria->issues);
    }

    public function test_hostile_get_shapes_emit_issue_codes_without_warnings(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $criteria = CatalogCriteria::fromInput([
                'q' => ['x'],
                'currency' => ['USD'],
                'category' => ['x'],
                'brand' => ['x'],
                'store' => ['x'],
                'min_price' => [1],
                'max_price' => [2],
                'availability' => ['in_stock'],
                'sort' => ['newest'],
                'attrs' => 'string',
                'unknown' => ['still', 'ignored'],
            ]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertNull($criteria->q);
        $this->assertNull($criteria->currencyCode);
        $this->assertNull($criteria->categorySlug);
        $this->assertNull($criteria->brandSlug);
        $this->assertNull($criteria->storeSlug);
        $this->assertNull($criteria->minPriceInput);
        $this->assertNull($criteria->maxPriceInput);
        $this->assertSame([], $criteria->attributes);
        $this->assertSame(CatalogSort::Newest, $criteria->sort);
        $this->assertSame(CatalogAvailability::Any, $criteria->availability);

        $this->assertContains(CatalogCriteriaIssueCode::Q_MALFORMED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::CURRENCY_MALFORMED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::CATEGORY_SLUG_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::BRAND_SLUG_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::STORE_SLUG_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_MIN_MALFORMED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_MAX_MALFORMED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::AVAILABILITY_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::SORT_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRS_MALFORMED, $criteria->issues);
        $this->assertSame(count($criteria->issues), count(array_unique($criteria->issues)));
    }

    public function test_invalid_slug_format_and_length_rejected(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'category' => 'Bad Slug!',
            'brand' => str_repeat('a', 121),
            'store' => 'not.valid',
        ]);

        $this->assertNull($criteria->categorySlug);
        $this->assertNull($criteria->brandSlug);
        $this->assertNull($criteria->storeSlug);
        $this->assertContains(CatalogCriteriaIssueCode::CATEGORY_SLUG_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::BRAND_SLUG_INVALID, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::STORE_SLUG_INVALID, $criteria->issues);
    }

    public function test_underscore_slugs_match_alpha_dash_ascii(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'category' => '-home__goods-',
            'brand' => '_acme--co_',
            'store' => str_repeat('a', 120),
        ]);

        $this->assertSame('-home__goods-', $criteria->categorySlug);
        $this->assertSame('_acme--co_', $criteria->brandSlug);
        $this->assertSame(str_repeat('a', 120), $criteria->storeSlug);
        $this->assertSame([], $criteria->issues);
    }

    public function test_attribute_iteration_bounded_early(): void
    {
        $attrs = [];
        for ($i = 0; $i < 10; $i++) {
            $values = [];
            for ($j = 0; $j < 20; $j++) {
                $values[] = 'v'.$j;
            }
            $attrs['attr'.$i] = $values;
        }

        $criteria = CatalogCriteria::fromInput(['attrs' => $attrs]);

        $this->assertCount(3, $criteria->attributes);
        foreach ($criteria->attributes as $values) {
            $this->assertCount(8, $values);
        }
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTES_LIMIT_EXCEEDED, $criteria->issues);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTE_VALUES_LIMIT_EXCEEDED, $criteria->issues);
        $this->assertSame(1, count(array_keys($criteria->issues, CatalogCriteriaIssueCode::ATTRIBUTES_LIMIT_EXCEEDED)));
        $this->assertSame(1, count(array_keys($criteria->issues, CatalogCriteriaIssueCode::ATTRIBUTE_VALUES_LIMIT_EXCEEDED)));
    }

    public function test_result_to_array_money_minors_are_strings_and_blocking_semantics(): void
    {
        $criteria = CatalogCriteria::fromInput([
            'q' => str_repeat('z', 90),
            'currency' => 'USD',
            'min_price' => '10.50',
            'max_price' => '20.00',
        ]);

        $harmless = new CatalogCriteriaResult(
            criteria: $criteria,
            issues: [],
            currencyCode: 'USD',
            currencyExponent: 2,
            minPriceMinor: 1050,
            maxPriceMinor: 2000,
        );

        $this->assertFalse($harmless->hasUnresolvedFilters());
        $this->assertFalse($harmless->hasBlockingIssues());
        $this->assertContains(CatalogCriteriaIssueCode::Q_TRUNCATED, $harmless->allIssues());

        $payload = $harmless->toArray();
        $this->assertSame('1050', $payload['criteria']['min_price_minor']);
        $this->assertSame('2000', $payload['criteria']['max_price_minor']);
        $this->assertIsString($payload['criteria']['min_price_minor']);
        $this->assertIsString($payload['criteria']['max_price_minor']);

        $blocking = new CatalogCriteriaResult(
            criteria: CatalogCriteria::fromInput(['category' => 'missing-leaf']),
            issues: [CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED],
            categoryUnresolved: true,
        );

        $this->assertTrue($blocking->hasUnresolvedFilters());
        $this->assertTrue($blocking->hasBlockingIssues());
        $this->assertContains(CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED, $blocking->allIssues());
    }

    public function test_effective_filters_drop_rejected_price_dependencies(): void
    {
        $range = new CatalogCriteriaResult(
            criteria: CatalogCriteria::fromInput([
                'currency' => 'USD',
                'min_price' => '30',
                'max_price' => '10',
            ]),
            issues: [CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX],
            currencyCode: 'USD',
            currencyExponent: 2,
        );

        $this->assertNull($range->effectiveCriteria()['min_price']);
        $this->assertNull($range->effectiveCriteria()['max_price']);
        $this->assertSame(['currency' => 'USD'], $range->toQueryParameters());

        $withoutCurrency = new CatalogCriteriaResult(
            criteria: CatalogCriteria::fromInput([
                'min_price' => '10',
                'sort' => 'price_desc',
            ]),
            issues: [],
        );

        $this->assertNull($withoutCurrency->effectiveCriteria()['min_price']);
        $this->assertSame(CatalogSort::Newest->value, $withoutCurrency->effectiveCriteria()['sort']);
        $this->assertSame([], $withoutCurrency->toQueryParameters());
    }

    public function test_presentation_payload_rejects_nested_models_and_collections(): void
    {
        $result = new CatalogCriteriaResult(criteria: CatalogCriteria::fromInput([]), issues: []);

        foreach ([
            [['nested' => ['model' => new Product]]],
            [['nested' => ['collection' => new Collection(['unsafe'])]]],
        ] as $payload) {
            try {
                $result->toArray($payload);
                $this->fail('Nested framework objects must be rejected.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('plain presentation arrays', $exception->getMessage());
            }
        }
    }
}
