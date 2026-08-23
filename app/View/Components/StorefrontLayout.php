<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StorefrontLayout extends Component
{
    public string $title;

    public string $description;

    public string $canonical;

    public string $robots;

    public string $ogTitle;

    public string $ogDescription;

    public string $ogType;

    public string $ogUrl;

    public ?string $ogImage;

    /**
     * @param  list<array<string, mixed>>  $navCategories
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $canonical = null,
        string $robots = 'index,follow',
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        string $ogType = 'website',
        ?string $ogUrl = null,
        ?string $ogImage = null,
        public array $navCategories = [],
        public ?string $searchQuery = null,
    ) {
        $this->title = $this->metadata($title ?? config('app.name'), 70);
        $this->description = $this->metadata(
            $description ?? __('Browse products from approved local stores.'),
            160,
        );
        $this->canonical = $this->absoluteUrl($canonical ?? url()->current());
        $this->robots = in_array($robots, ['index,follow', 'noindex,follow'], true)
            ? $robots
            : 'noindex,follow';
        $this->ogTitle = $this->metadata($ogTitle ?? $this->title, 70);
        $this->ogDescription = $this->metadata($ogDescription ?? $this->description, 160);
        $this->ogType = in_array($ogType, ['website', 'product'], true) ? $ogType : 'website';
        $this->ogUrl = $this->absoluteUrl($ogUrl ?? $this->canonical);
        $this->ogImage = filled($ogImage) ? $this->absoluteUrl((string) $ogImage) : null;
    }

    public function render(): View
    {
        return view('layouts.storefront');
    }

    private function metadata(string $value, int $maxLength): string
    {
        $plain = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = trim((string) preg_replace('/\s+/u', ' ', $plain));

        return mb_substr($plain, 0, $maxLength);
    }

    private function absoluteUrl(string $value): string
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true)
        ) {
            return $value;
        }

        return url('/'.ltrim($value, '/'));
    }
}
