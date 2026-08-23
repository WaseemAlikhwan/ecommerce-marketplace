@php
    $isEdit = isset($product) && $product;
    $readOnly = $isEdit && ! $canEdit;
@endphp

<x-ui.card>
    <fieldset>
        <legend class="mb-2 text-heading-3">{{ __('Variant matrix') }}</legend>
        <p class="mb-4 text-sm text-ink-muted">{{ __('Choose up to three attributes, select values, generate combinations, then set SKU, price, quantity, and the default variant.') }}</p>

        <div x-cloak x-show="frozen" class="mb-4">
            <x-ui.alert tone="warning" :title="__('Attribute topology is frozen')">
                {{ __('After first publication you can edit prices, SKUs, quantity, and the default, and archive or restore known combinations. You cannot add new attributes, values, or combinations.') }}
            </x-ui.alert>
        </div>

        <div x-cloak x-show="dictionary.length === 0">
            <x-ui.empty-state :title="__('No global attributes yet')">
                {{ __('Variable products need global attributes configured by administration. Ask an admin to add attributes such as color or size before creating variants.') }}
            </x-ui.empty-state>
        </div>

        <div x-cloak x-show="dictionary.length > 0" class="space-y-6">
            <section>
                <h3 class="mb-3 text-sm font-medium">{{ __('1. Choose attributes') }}</h3>
                <div class="flex flex-wrap gap-2">
                    <template x-for="attribute in dictionary" :key="attribute.id">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-pill border border-line px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                            <input
                                type="checkbox"
                                class="ds-checkbox"
                                :checked="isAttributeSelected(attribute.id)"
                                :disabled="!canEdit || frozen || (!isAttributeSelected(attribute.id) && attributeLimitReached) || (!attribute.isActive && !isAttributeSelected(attribute.id))"
                                @change="toggleAttribute(attribute.id)"
                            >
                            <span x-text="attribute.name"></span>
                            <span class="text-caption text-ink-muted" dir="ltr" x-text="attribute.code"></span>
                            <span x-cloak x-show="!attribute.isActive" class="rounded-pill border border-warning/30 bg-warning-soft px-1.5 text-caption text-warning">{{ __('Inactive') }}</span>
                        </label>
                    </template>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('attributes')" />
            </section>

            <section x-show="selectedAttributes.length > 0">
                <h3 class="mb-3 text-sm font-medium">{{ __('2. Choose values') }}</h3>
                <div class="space-y-4">
                    <template x-for="attribute in selectedAttributes" :key="'values-'+attribute.id">
                        <div>
                            <p class="mb-2 font-medium" x-text="attribute.name"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="value in attribute.values" :key="value.id">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-pill border border-line px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                        <input
                                            type="checkbox"
                                            class="ds-checkbox"
                                            :checked="isValueSelected(attribute.id, value.id)"
                                            :disabled="!canEdit || frozen || (!isValueSelected(attribute.id, value.id) && valueLimitReached(attribute.id)) || (!value.isActive && !isValueSelected(attribute.id, value.id))"
                                            @change="toggleValue(attribute.id, value.id)"
                                        >
                                        <span x-text="value.name"></span>
                                        <span class="text-caption text-ink-muted" dir="ltr" x-text="value.code"></span>
                                        <span x-cloak x-show="!value.isActive" class="rounded-pill border border-warning/30 bg-warning-soft px-1.5 text-caption text-warning">{{ __('Inactive') }}</span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <section>
                <h3 class="mb-3 text-sm font-medium">{{ __('3. Generate combinations') }}</h3>
                <p class="mb-3 text-sm" aria-live="polite" aria-atomic="true">
                    <span x-text="labels.combinations || '{{ __('Combinations') }}'"></span>:
                    <strong x-text="cartesianCount"></strong>
                    /
                    <span x-text="maxCartesian"></span>
                </p>
                <p x-cloak x-show="cartesianBlocked" class="mb-3 text-sm text-danger">{{ __('Too many combinations. Reduce attributes or values to 48 or fewer.') }}</p>
                @unless ($readOnly)
                    <button
                        type="button"
                        class="ds-button-secondary"
                        x-show="!frozen"
                        :disabled="cartesianBlocked || cartesianCount < 1"
                        @click="generate()"
                    >
                        {{ __('Generate combinations') }}
                    </button>
                @endunless
            </section>

            <template x-if="type === 'variable'">
                <div>
                    <template x-for="(attribute, attributeIndex) in selectedAttributes" :key="'hidden-attr-'+attribute.id">
                        <div>
                            <input type="hidden" :name="'attributes['+attributeIndex+'][attribute_id]'" :value="attribute.id">
                            <template x-for="valueId in (selectedValueIds[attribute.id] || [])" :key="'hidden-val-'+attribute.id+'-'+valueId">
                                <input type="hidden" :name="'attributes['+attributeIndex+'][value_ids][]'" :value="valueId">
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <section x-show="generated || rows.length > 0">
                <h3 class="mb-3 text-sm font-medium">{{ __('4. Configure variants') }}</h3>

                @unless ($readOnly)
                    <div class="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <x-input-label for="sku_prefix" :value="__('SKU prefix')" />
                            <input id="sku_prefix" type="text" class="ds-input" dir="ltr" x-model="skuPrefix" :disabled="!canEdit">
                        </div>
                        <div>
                            <x-input-label for="bulk_price" :value="__('Price')" />
                            <input id="bulk_price" type="text" class="ds-input" dir="ltr" x-model="bulkPrice" :disabled="!canEdit">
                        </div>
                        <div>
                            <x-input-label for="bulk_compare" :value="__('Compare-at price')" />
                            <input id="bulk_compare" type="text" class="ds-input" dir="ltr" x-model="bulkCompare" :disabled="!canEdit">
                        </div>
                        <div>
                            <x-input-label for="bulk_quantity" :value="__('Quantity')" />
                            <input id="bulk_quantity" type="number" min="0" class="ds-input" dir="ltr" x-model="bulkQuantity" :disabled="!canEdit">
                        </div>
                    </div>
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button type="button" class="ds-button-secondary text-sm" @click="fillMissingSkus()" :disabled="!canEdit">{{ __('Fill missing SKUs') }}</button>
                        <button type="button" class="ds-button-secondary text-sm" @click="applyPriceToBlank()" :disabled="!canEdit">{{ __('Apply price to blank rows') }}</button>
                        <button type="button" class="ds-button-secondary text-sm" @click="applyCompareToBlank()" :disabled="!canEdit">{{ __('Apply compare-at to blank rows') }}</button>
                        <button type="button" class="ds-button-secondary text-sm" @click="applyQuantityToBlank()" :disabled="!canEdit">{{ __('Apply quantity to blank rows') }}</button>
                    </div>
                @endunless

                <div class="hidden overflow-x-auto md:block">
                    <table class="ds-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Include') }}</th>
                                <th scope="col">{{ __('Combination') }}</th>
                                <th scope="col">{{ __('SKU') }}</th>
                                <th scope="col">{{ __('Price') }}</th>
                                <th scope="col">{{ __('Compare-at price') }}</th>
                                <th scope="col">{{ __('Quantity') }}</th>
                                <th scope="col">{{ __('Default') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in includedRows" :key="'table-'+row.key">
                                <tr>
                                    <td>
                                        <input type="checkbox" class="ds-checkbox" :checked="row.included" :disabled="!canExclude(row)" @change="toggleIncluded(row)">
                                        <input type="hidden" :name="'variants['+index+'][is_default]'" :value="row.isDefault ? 1 : 0">
                                        <template x-for="valueId in row.valueIds" :key="'tv-'+row.key+'-'+valueId">
                                            <input type="hidden" :name="'variants['+index+'][value_ids][]'" :value="valueId">
                                        </template>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="chip in row.chips" :key="chip.label">
                                                <span class="inline-flex items-center gap-1 rounded-pill border border-line px-2 py-0.5 text-caption">
                                                    <span x-text="chip.label"></span>
                                                    <span class="text-ink-muted" dir="ltr" x-text="chip.code"></span>
                                                    <span x-cloak x-show="chip.inactive" class="text-warning">{{ __('Inactive') }}</span>
                                                </span>
                                            </template>
                                        </div>
                                    </td>
                                    <td><input type="text" class="ds-input" dir="ltr" :name="'variants['+index+'][sku]'" x-model="row.sku" :disabled="!canEdit" required></td>
                                    <td><input type="text" class="ds-input" dir="ltr" :name="'variants['+index+'][price]'" x-model="row.price" :disabled="!canEdit" required></td>
                                    <td><input type="text" class="ds-input" dir="ltr" :name="'variants['+index+'][compare_at_price]'" x-model="row.compareAt" :disabled="!canEdit"></td>
                                    <td><input type="number" min="0" class="ds-input" dir="ltr" :name="'variants['+index+'][quantity]'" x-model="row.quantity" :disabled="!canEdit" required></td>
                                    <td>
                                        <input type="radio" name="matrix_default" class="ds-radio" :checked="row.isDefault" :disabled="!canEdit" @change="setDefault(row)">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    <template x-for="(row, index) in includedRows" :key="'card-'+row.key">
                        <article class="ds-card p-4">
                            <div class="mb-3 flex flex-wrap gap-1">
                                <template x-for="chip in row.chips" :key="'c-'+chip.label">
                                    <span class="inline-flex items-center gap-1 rounded-pill border border-line px-2 py-0.5 text-caption">
                                        <span x-text="chip.label"></span>
                                        <span class="text-ink-muted" dir="ltr" x-text="chip.code"></span>
                                    </span>
                                </template>
                            </div>
                            <label class="mb-2 flex items-center gap-2 text-sm">
                                <input type="checkbox" class="ds-checkbox" :checked="row.included" :disabled="!canExclude(row)" @change="toggleIncluded(row)">
                                {{ __('Include') }}
                            </label>
                            <label class="mb-2 flex items-center gap-2 text-sm">
                                <input type="radio" name="matrix_default_mobile" class="ds-radio" :checked="row.isDefault" :disabled="!canEdit" @change="setDefault(row)">
                                {{ __('Default variant') }}
                            </label>
                            <div class="grid gap-3">
                                <div>
                                    <span class="text-caption text-ink-muted">{{ __('SKU') }}</span>
                                    <input type="text" class="ds-input" dir="ltr" x-model="row.sku" :disabled="!canEdit">
                                </div>
                                <div>
                                    <span class="text-caption text-ink-muted">{{ __('Price') }}</span>
                                    <input type="text" class="ds-input" dir="ltr" x-model="row.price" :disabled="!canEdit">
                                </div>
                                <div>
                                    <span class="text-caption text-ink-muted">{{ __('Compare-at price') }}</span>
                                    <input type="text" class="ds-input" dir="ltr" x-model="row.compareAt" :disabled="!canEdit">
                                </div>
                                <div>
                                    <span class="text-caption text-ink-muted">{{ __('Quantity') }}</span>
                                    <input type="number" min="0" class="ds-input" dir="ltr" x-model="row.quantity" :disabled="!canEdit">
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-cloak x-show="excludedRows.length > 0" class="mt-6">
                    <h4 class="mb-3 text-sm font-medium">{{ __('Excluded combinations') }}</h4>
                    <ul class="space-y-2">
                        <template x-for="row in excludedRows" :key="'excl-'+row.key">
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-line px-3 py-2">
                                <div class="space-y-1">
                                    <span class="rounded-pill border border-line px-2 py-0.5 text-caption" x-text="excludedStatusLabel(row)"></span>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="chip in row.chips" :key="'a-'+chip.label">
                                            <span class="text-caption" x-text="chip.label"></span>
                                        </template>
                                    </div>
                                    <p x-cloak x-show="excludedRowAction(row) === 'restore_blocked'" class="text-caption text-warning">{{ __('Cannot restore while an attribute or value is inactive.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="ds-button-secondary text-sm"
                                    x-show="canReinclude(row)"
                                    x-text="excludedRowLabel(row)"
                                    @click="toggleIncluded(row)"
                                ></button>
                            </li>
                        </template>
                    </ul>
                </div>

                <x-input-error class="mt-3" :messages="$errors->get('variants')" />
                <x-input-error :messages="$errors->get('default_variant')" />
            </section>
        </div>
    </fieldset>
</x-ui.card>
