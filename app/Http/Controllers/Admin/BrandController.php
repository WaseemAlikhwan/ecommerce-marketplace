<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        $brands = Brand::query()
            ->with('translations')
            ->searchByName($request->string('q')->toString())
            ->when(
                $request->string('status')->toString() === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $request->string('status')->toString() === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.brands.index', [
            'brands' => $brands,
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Brand::class);

        return view('admin.brands.create');
    }

    public function store(StoreBrandRequest $request, BrandService $brands): RedirectResponse
    {
        $brand = $brands->create($this->payload($request->validated()));

        return redirect()
            ->route('admin.brands.edit', $brand)
            ->with('status', __('Brand created.'));
    }

    public function edit(Brand $brand): View
    {
        $this->authorize('update', $brand);

        $brand->load('translations');

        return view('admin.brands.edit', [
            'brand' => $brand,
            'translations' => [
                'ar' => $brand->translations->firstWhere('locale', 'ar'),
                'en' => $brand->translations->firstWhere('locale', 'en'),
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand, BrandService $brands): RedirectResponse
    {
        $brands->update($brand, $this->payload($request->validated()));

        return redirect()
            ->route('admin.brands.edit', $brand)
            ->with('status', __('Brand updated.'));
    }

    public function updateStatus(Request $request, Brand $brand, BrandService $brands): RedirectResponse
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $brands->setActive($brand, (bool) $validated['is_active']);

        return back()->with('status', $validated['is_active']
            ? __('Brand activated.')
            : __('Brand deactivated.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{slug: ?string, is_active: bool, translations: array<string, array{name: string, description: ?string}>}
     */
    private function payload(array $validated): array
    {
        return [
            'slug' => $validated['slug'] ?? null,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
            'translations' => [
                'ar' => [
                    'name' => $validated['translations']['ar']['name'],
                    'description' => $validated['translations']['ar']['description'] ?? null,
                ],
                'en' => [
                    'name' => $validated['translations']['en']['name'],
                    'description' => $validated['translations']['en']['description'] ?? null,
                ],
            ],
        ];
    }
}
