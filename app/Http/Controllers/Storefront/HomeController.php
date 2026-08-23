<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontHomeService;
use Illuminate\Contracts\View\View;

final class HomeController extends Controller
{
    public function __construct(
        private readonly StorefrontHomeService $home,
    ) {}

    public function __invoke(): View
    {
        $locale = app()->getLocale();
        $home = $this->home->get($locale);

        return view('storefront.home', [
            'home' => $home,
            'navCategories' => $home['navigation'],
        ]);
    }
}
