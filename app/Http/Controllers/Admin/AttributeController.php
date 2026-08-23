<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Services\AttributeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttributeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Attribute::class);

        $attributes = Attribute::query()
            ->with('translations')
            ->withCount('values')
            ->searchByName($request->string('q')->toString())
            ->when(
                $request->string('status')->toString() === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $request->string('status')->toString() === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.attributes.index', [
            'attributes' => $attributes,
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Attribute::class);

        return view('admin.attributes.create');
    }

    public function store(StoreAttributeRequest $request, AttributeService $attributes): RedirectResponse
    {
        $attribute = $attributes->create($this->payload($request->validated()));

        return redirect()
            ->route('admin.attributes.show', $attribute)
            ->with('status', __('Attribute created.'));
    }

    public function show(Attribute $attribute): View
    {
        $this->authorize('view', $attribute);

        $attribute->load('translations');

        $values = $attribute->values()
            ->with('translations')
            ->searchByName(request()->string('q')->toString())
            ->when(
                request()->string('status')->toString() === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                request()->string('status')->toString() === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.attributes.show', [
            'attribute' => $attribute,
            'values' => $values,
            'q' => request()->string('q')->toString(),
            'status' => request()->string('status')->toString(),
        ]);
    }

    public function edit(Attribute $attribute): View
    {
        $this->authorize('update', $attribute);

        $attribute->load('translations');

        return view('admin.attributes.edit', [
            'attribute' => $attribute,
            'translations' => [
                'ar' => $attribute->translations->firstWhere('locale', 'ar'),
                'en' => $attribute->translations->firstWhere('locale', 'en'),
            ],
        ]);
    }

    public function update(
        UpdateAttributeRequest $request,
        Attribute $attribute,
        AttributeService $attributes,
    ): RedirectResponse {
        $attributes->update($attribute, $this->payload($request->validated()));

        return redirect()
            ->route('admin.attributes.edit', $attribute)
            ->with('status', __('Attribute updated.'));
    }

    public function updateStatus(Request $request, Attribute $attribute, AttributeService $attributes): RedirectResponse
    {
        $this->authorize('update', $attribute);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $attributes->setActive($attribute, (bool) $validated['is_active']);

        return back()->with('status', $validated['is_active']
            ? __('Attribute activated.')
            : __('Attribute deactivated.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{code: ?string, position: int, is_active: bool, translations: array<string, array{name: string}>}
     */
    private function payload(array $validated): array
    {
        return [
            'code' => $validated['code'] ?? null,
            'position' => (int) ($validated['position'] ?? 0),
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
            'translations' => [
                'ar' => ['name' => $validated['translations']['ar']['name']],
                'en' => ['name' => $validated['translations']['en']['name']],
            ],
        ];
    }
}
