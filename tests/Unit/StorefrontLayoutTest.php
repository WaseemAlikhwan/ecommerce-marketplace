<?php

namespace Tests\Unit;

use App\View\Components\StorefrontLayout;
use Tests\TestCase;

class StorefrontLayoutTest extends TestCase
{
    public function test_metadata_is_plain_bounded_and_uses_safe_absolute_urls(): void
    {
        $layout = new StorefrontLayout(
            title: '<script>alert(1)</script>'.str_repeat('x', 100),
            description: '&lt;b&gt;'.str_repeat('description ', 30).'&lt;/b&gt;',
            canonical: 'javascript:alert(1)',
            robots: 'invalid',
            ogTitle: '<em>Open Graph</em>',
            ogDescription: str_repeat('og ', 100),
            ogType: 'invalid',
            ogUrl: '//untrusted.example/path',
            ogImage: 'data:image/svg+xml,unsafe',
        );

        $this->assertStringNotContainsString('<', $layout->title);
        $this->assertStringNotContainsString('<', $layout->description);
        $this->assertLessThanOrEqual(70, mb_strlen($layout->title));
        $this->assertLessThanOrEqual(160, mb_strlen($layout->description));
        $this->assertLessThanOrEqual(160, mb_strlen($layout->ogDescription));
        $this->assertSame('noindex,follow', $layout->robots);
        $this->assertSame('website', $layout->ogType);
        $this->assertStringStartsWith(url('/'), $layout->canonical);
        $this->assertStringStartsWith(url('/'), $layout->ogUrl);
        $this->assertStringStartsWith(url('/'), (string) $layout->ogImage);
        $this->assertStringNotContainsString('untrusted.example', parse_url($layout->ogUrl, PHP_URL_HOST) ?: '');
    }

    public function test_valid_absolute_metadata_urls_are_preserved(): void
    {
        $layout = new StorefrontLayout(
            canonical: 'https://shop.example/catalog',
            ogUrl: 'https://shop.example/open-graph',
            ogImage: 'https://cdn.example/product.jpg',
        );

        $this->assertSame('https://shop.example/catalog', $layout->canonical);
        $this->assertSame('https://shop.example/open-graph', $layout->ogUrl);
        $this->assertSame('https://cdn.example/product.jpg', $layout->ogImage);
    }
}
