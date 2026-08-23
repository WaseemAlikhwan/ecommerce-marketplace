<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Category::class);
        $this->authorize('viewAny', Brand::class);
        $this->authorize('viewAny', Attribute::class);

        return view('admin.catalog.index', [
            'categoryCount' => Category::query()->count(),
            'brandCount' => Brand::query()->count(),
            'attributeCount' => Attribute::query()->count(),
            'activeCategoryCount' => Category::query()->active()->count(),
            'activeBrandCount' => Brand::query()->active()->count(),
            'activeAttributeCount' => Attribute::query()->active()->count(),
        ]);
    }
}
