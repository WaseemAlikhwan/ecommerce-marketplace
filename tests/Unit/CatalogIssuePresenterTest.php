<?php

namespace Tests\Unit;

use App\Storefront\CatalogCriteriaIssueCode;
use App\Storefront\Presentation\CatalogIssuePresenter;
use ReflectionClass;
use Tests\TestCase;

class CatalogIssuePresenterTest extends TestCase
{
    public function test_every_issue_code_has_arabic_and_english_public_copy(): void
    {
        $presenter = app(CatalogIssuePresenter::class);
        $codes = array_values((new ReflectionClass(CatalogCriteriaIssueCode::class))->getConstants());

        foreach ($codes as $code) {
            $english = $presenter->present($code, 'en');
            $arabic = $presenter->present($code, 'ar');

            $this->assertSame($code, $english['code']);
            $this->assertContains($english['kind'], ['malformed', 'unresolved']);
            $this->assertNotSame('Some filters could not be applied.', $english['message']);
            $this->assertNotSame($english['message'], $arabic['message']);
        }
    }

    public function test_unresolved_and_malformed_issues_are_distinct(): void
    {
        $presenter = app(CatalogIssuePresenter::class);

        $this->assertSame(
            'malformed',
            $presenter->present(CatalogCriteriaIssueCode::Q_MALFORMED, 'en')['kind'],
        );
        $this->assertSame(
            'unresolved',
            $presenter->present(CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED, 'en')['kind'],
        );
    }
}
