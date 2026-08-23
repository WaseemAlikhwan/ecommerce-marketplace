<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeValueRequest;
use App\Http\Requests\Admin\UpdateAttributeValueRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\AttributeValueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttributeValueController extends Controller
{
    public function create(Attribute $attribute): View
    {
        $this->authorize('view', $attribute);
        $this->authorize('create', AttributeValue::class);

        return view('admin.attribute-values.create', [
            'attribute' => $attribute,
        ]);
    }

    public function store(
        StoreAttributeValueRequest $request,
        Attribute $attribute,
        AttributeValueService $values,
    ): RedirectResponse {
        $values->create($attribute, $this->payload($request->validated()));

        return redirect()
            ->route('admin.attributes.show', $attribute)
            ->with('status', __('Attribute value created.'));
    }

    public function edit(Attribute $attribute, AttributeValue $attributeValue): View
    {
        $this->assertBelongsToAttribute($attribute, $attributeValue);
        $this->authorize('update', $attributeValue);

        $attributeValue->load('translations');

        return view('admin.attribute-values.edit', [
            'attribute' => $attribute,
            'value' => $attributeValue,
            'translations' => [
                'ar' => $attributeValue->translations->firstWhere('locale', 'ar'),
                'en' => $attributeValue->translations->firstWhere('locale', 'en'),
            ],
        ]);
    }

    public function update(
        UpdateAttributeValueRequest $request,
        Attribute $attribute,
        AttributeValue $attributeValue,
        AttributeValueService $values,
    ): RedirectResponse {
        $values->update($attribute, $attributeValue, $this->payload($request->validated()));

        return redirect()
            ->route('admin.attribute-values.edit', [$attribute, $attributeValue])
            ->with('status', __('Attribute value updated.'));
    }

    public function updateStatus(
        Request $request,
        Attribute $attribute,
        AttributeValue $attributeValue,
        AttributeValueService $values,
    ): RedirectResponse {
        $this->assertBelongsToAttribute($attribute, $attributeValue);
        $this->authorize('update', $attributeValue);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $values->setActive($attribute, $attributeValue, (bool) $validated['is_active']);

        return back()->with('status', $validated['is_active']
            ? __('Attribute value activated.')
            : __('Attribute value deactivated.'));
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

    private function assertBelongsToAttribute(Attribute $attribute, AttributeValue $value): void
    {
        abort_unless($value->attribute_id === $attribute->id, 404);
    }
}
