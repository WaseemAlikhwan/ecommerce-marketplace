<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CatalogTaxonomyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with(['translations', 'parent.translations'])
            ->searchByName($request->string('q')->toString())
            ->when(
                $request->string('status')->toString() === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $request->string('status')->toString() === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', [
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): RedirectResponse
    {
        try {
            $category = $categories->create($this->payload($request->validated()));
        } catch (CatalogTaxonomyException $exception) {
            return back()->withInput()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('status', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        $category->load('translations');

        return view('admin.categories.edit', [
            'category' => $category,
            'parentOptions' => $this->parentOptions($category),
            'translations' => [
                'ar' => $category->translations->firstWhere('locale', 'ar'),
                'en' => $category->translations->firstWhere('locale', 'en'),
            ],
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        CategoryService $categories,
    ): RedirectResponse {
        try {
            $categories->update($category, $this->payload($request->validated()));
        } catch (CatalogTaxonomyException $exception) {
            return back()->withInput()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('status', __('Category updated.'));
    }

    public function updateStatus(Request $request, Category $category, CategoryService $categories): RedirectResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $categories->setActive($category, (bool) $validated['is_active']);

        return back()->with('status', $validated['is_active']
            ? __('Category activated.')
            : __('Category deactivated.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{parent_id: int|null, slug: ?string, position: int, is_active: bool, translations: array<string, array{name: string, description: ?string}>}
     */
    private function payload(array $validated): array
    {
        return [
            'parent_id' => $validated['parent_id'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'position' => (int) ($validated['position'] ?? 0),
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

    /**
     * @return list<Category>
     */
    private function parentOptions(?Category $exclude = null): array
    {
        $excludeIds = collect();

        if ($exclude !== null) {
            $excludeIds = $exclude->descendantIds()->push($exclude->id);
        }

        return Category::query()
            ->with(['translations', 'parent.parent'])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->reject(fn (Category $category): bool => $excludeIds->contains($category->id))
            ->filter(fn (Category $category): bool => $category->depth() < Category::MAX_DEPTH)
            ->values()
            ->all();
    }
}
