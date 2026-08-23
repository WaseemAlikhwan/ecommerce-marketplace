export function registerVendorProductForm(Alpine) {
    Alpine.data('vendorProductForm', (config) => ({
        type: config.type || 'simple',
        lockedType: Boolean(config.lockedType),
        canEdit: config.canEdit !== false,
        frozen: Boolean(config.frozen),
        currencyCode: config.currencyCode,
        initialCurrencyCode: config.initialCurrencyCode,
        exponents: config.exponents || {},
        maxAttributes: config.maxAttributes || 3,
        maxValues: config.maxValues || 8,
        maxCartesian: config.maxCartesian || 48,
        dictionary: config.dictionary || [],
        selectedAttributeIds: [...(config.selectedAttributeIds || [])],
        selectedValueIds: { ...(config.selectedValueIds || {}) },
        rows: (config.rows || []).map((row) => ({ ...row, valueMap: { ...(row.valueMap || {}) } })),
        generated: Boolean(config.generated),
        skuPrefix: '',
        bulkPrice: '',
        bulkCompare: '',
        bulkQuantity: '',
        labels: config.labels || {},
        formDirty: Boolean(config.initialDirty),
        snapshot: '',
        ready: false,
        latchedDirty: Boolean(config.initialDirty),

        init() {
            this.$watch('type', () => this.recomputeAfterProgrammaticChange());
            this.$watch('currencyCode', () => this.recomputeAfterProgrammaticChange());
            this.$watch('selectedAttributeIds', () => this.recomputeAfterProgrammaticChange(), { deep: true });
            this.$watch('selectedValueIds', () => this.recomputeAfterProgrammaticChange(), { deep: true });
            this.$watch('rows', () => this.recomputeAfterProgrammaticChange(), { deep: true });

            this.$nextTick(() => {
                this.snapshot = this.serializeForm();
                this.formDirty = Boolean(config.initialDirty) || this.latchedDirty;
                this.emitDirty();
                this.ready = true;

                this.$el.addEventListener('input', () => this.markDirtyFromDom());
                this.$el.addEventListener('change', () => this.markDirtyFromDom());
            });
        },

        serializeForm() {
            try {
                return [...new FormData(this.$el).entries()]
                    .map(([key, value]) => `${key}=${value}`)
                    .join('&');
            } catch {
                return '';
            }
        },

        markDirtyFromDom() {
            if (!this.ready) {
                return;
            }

            if (this.latchedDirty) {
                this.formDirty = true;
                this.emitDirty();
                return;
            }

            const next = this.serializeForm();
            this.formDirty = next !== this.snapshot;
            this.emitDirty();
        },

        recomputeAfterProgrammaticChange() {
            if (!this.ready) {
                return;
            }

            this.$nextTick(() => {
                if (this.latchedDirty) {
                    this.formDirty = true;
                    this.emitDirty();
                    return;
                }

                const next = this.serializeForm();
                this.formDirty = next !== this.snapshot;
                this.emitDirty();
            });
        },

        discardChanges() {
            const message = this.labels.reloadConfirm
                || 'Discard unsaved product details and reload the saved version?';

            if (!window.confirm(message)) {
                return;
            }

            window.location.reload();
        },

        emitDirty() {
            window.dispatchEvent(new CustomEvent('vendor-product-form-dirty', {
                detail: { dirty: this.formDirty },
            }));
        },

        get selectedAttributes() {
            return this.selectedAttributeIds
                .map((id) => this.dictionary.find((attribute) => Number(attribute.id) === Number(id)))
                .filter(Boolean);
        },

        get cartesianCount() {
            if (this.selectedAttributes.length === 0) {
                return 0;
            }

            return this.selectedAttributes.reduce((total, attribute) => {
                const count = (this.selectedValueIds[attribute.id] || []).length;

                return total * count;
            }, 1);
        },

        get cartesianBlocked() {
            return this.cartesianCount > this.maxCartesian;
        },

        get includedRows() {
            return this.rows.filter((row) => row.included);
        },

        get excludedRows() {
            return this.rows.filter((row) => !row.included);
        },

        get currencyChanged() {
            return String(this.currencyCode) !== String(this.initialCurrencyCode);
        },

        get attributeLimitReached() {
            return this.selectedAttributeIds.length >= this.maxAttributes;
        },

        isAttributeSelected(id) {
            return this.selectedAttributeIds.some((selected) => Number(selected) === Number(id));
        },

        isValueSelected(attributeId, valueId) {
            return (this.selectedValueIds[attributeId] || []).some((id) => Number(id) === Number(valueId));
        },

        valueLimitReached(attributeId) {
            return (this.selectedValueIds[attributeId] || []).length >= this.maxValues;
        },

        toggleAttribute(id) {
            if (!this.canEdit || this.frozen) {
                return;
            }

            if (this.isAttributeSelected(id)) {
                this.selectedAttributeIds = this.selectedAttributeIds.filter((selected) => Number(selected) !== Number(id));
                const nextValues = { ...this.selectedValueIds };
                delete nextValues[id];
                this.selectedValueIds = nextValues;
                return;
            }

            if (this.attributeLimitReached) {
                return;
            }

            this.selectedAttributeIds = [...this.selectedAttributeIds, Number(id)];
            this.selectedValueIds = { ...this.selectedValueIds, [id]: this.selectedValueIds[id] || [] };
        },

        toggleValue(attributeId, valueId) {
            if (!this.canEdit || this.frozen) {
                return;
            }

            const current = [...(this.selectedValueIds[attributeId] || [])];
            const exists = current.some((id) => Number(id) === Number(valueId));

            if (exists) {
                this.selectedValueIds = {
                    ...this.selectedValueIds,
                    [attributeId]: current.filter((id) => Number(id) !== Number(valueId)),
                };
                return;
            }

            if (current.length >= this.maxValues) {
                return;
            }

            this.selectedValueIds = {
                ...this.selectedValueIds,
                [attributeId]: [...current, Number(valueId)],
            };
        },

        generate() {
            if (!this.canEdit || this.frozen || this.cartesianBlocked || this.cartesianCount < 1) {
                return;
            }

            const valueGroups = this.selectedAttributes.map((attribute) => this.selectedValueIds[attribute.id] || []);
            const combinations = valueGroups.reduce(
                (accumulator, values) => accumulator.flatMap((prefix) => values.map((valueId) => [...prefix, Number(valueId)])),
                [[]],
            );

            const existingByKey = new Map(this.rows.map((row) => [row.key, row]));
            const nextRows = combinations.map((valueIds) => {
                const valueMap = {};
                this.selectedAttributes.forEach((attribute, index) => {
                    valueMap[attribute.id] = valueIds[index];
                });
                const key = Object.keys(valueMap)
                    .map(Number)
                    .sort((a, b) => a - b)
                    .map((attributeId) => valueMap[attributeId])
                    .join('|');
                const existing = existingByKey.get(key);

                if (existing) {
                    return {
                        ...existing,
                        valueIds: Object.keys(valueMap)
                            .map(Number)
                            .sort((a, b) => a - b)
                            .map((attributeId) => valueMap[attributeId]),
                        valueMap,
                        included: existing.archived && existing.canRestore === false ? false : Boolean(existing.included),
                        chips: this.chipsFor(valueMap),
                    };
                }

                return {
                    key,
                    valueIds: Object.keys(valueMap)
                        .map(Number)
                        .sort((a, b) => a - b)
                        .map((attributeId) => valueMap[attributeId]),
                    valueMap,
                    sku: '',
                    price: '',
                    compareAt: '',
                    quantity: 0,
                    included: true,
                    isDefault: false,
                    persisted: false,
                    archived: false,
                    canRestore: false,
                    inactiveGlobals: false,
                    chips: this.chipsFor(valueMap),
                };
            });

            if (!nextRows.some((row) => row.isDefault && row.included) && nextRows.length > 0) {
                nextRows[0].isDefault = true;
            }

            this.rows = nextRows;
            this.generated = true;
        },

        chipsFor(valueMap) {
            return this.selectedAttributes.map((attribute) => {
                const valueId = valueMap[attribute.id];
                const value = (attribute.values || []).find((item) => Number(item.id) === Number(valueId));

                return {
                    label: `${attribute.name}: ${value?.name || valueId}`,
                    code: value?.code || '',
                    inactive: attribute.isActive === false || value?.isActive === false,
                };
            });
        },

        setDefault(row) {
            if (!this.canEdit || !row.included) {
                return;
            }

            this.rows = this.rows.map((item) => ({
                ...item,
                isDefault: item.key === row.key,
            }));
        },

        canExclude(row) {
            return Boolean(this.canEdit && row.included && this.includedRows.length > 1);
        },

        canReinclude(row) {
            if (!this.canEdit || row.included) {
                return false;
            }

            return this.excludedRowAction(row) !== 'restore_blocked';
        },

        excludedRowAction(row) {
            if (row.archived) {
                return row.canRestore ? 'restore_archived' : 'restore_blocked';
            }

            return 'undo_exclusion';
        },

        excludedRowLabel(row) {
            const action = this.excludedRowAction(row);

            if (action === 'restore_archived') {
                return this.labels.restoreArchived || 'Restore archived combination';
            }

            if (action === 'undo_exclusion') {
                return this.labels.undoExclusion || 'Undo exclusion';
            }

            return this.labels.restoreBlocked || 'Cannot restore while an attribute or value is inactive.';
        },

        excludedStatusLabel(row) {
            if (row.archived) {
                return this.labels.archivedCombination || 'Archived combination';
            }

            if (row.persisted) {
                return this.labels.temporarilyExcluded || 'Temporarily excluded';
            }

            return this.labels.newCombination || 'New combination';
        },

        toggleIncluded(row) {
            if (!this.canEdit) {
                return;
            }

            if (row.included) {
                if (!this.canExclude(row)) {
                    return;
                }

                row.included = false;
                if (row.isDefault) {
                    row.isDefault = false;
                    const next = this.includedRows[0];
                    if (next) {
                        next.isDefault = true;
                    }
                }

                return;
            }

            if (!this.canReinclude(row)) {
                return;
            }

            row.included = true;
        },

        suggestionFor(row) {
            const codes = this.selectedAttributes.map((attribute) => {
                const valueId = row.valueMap[attribute.id];
                const value = (attribute.values || []).find((item) => Number(item.id) === Number(valueId));

                return String(value?.code || valueId || '')
                    .toUpperCase()
                    .replace(/[^A-Z0-9]+/g, '-');
            });
            const prefix = String(this.skuPrefix || '')
                .trim()
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '-');

            return [prefix, ...codes].filter(Boolean).join('-').slice(0, 64);
        },

        fillMissingSkus() {
            if (!this.canEdit) {
                return;
            }

            this.includedRows.forEach((row) => {
                if (!String(row.sku || '').trim()) {
                    row.sku = this.suggestionFor(row);
                }
            });
        },

        applyPriceToBlank() {
            if (!this.canEdit || !String(this.bulkPrice || '').trim()) {
                return;
            }

            this.includedRows.forEach((row) => {
                if (!String(row.price || '').trim()) {
                    row.price = String(this.bulkPrice).trim();
                }
            });
        },

        applyCompareToBlank() {
            if (!this.canEdit || !String(this.bulkCompare || '').trim()) {
                return;
            }

            this.includedRows.forEach((row) => {
                if (!String(row.compareAt || '').trim()) {
                    row.compareAt = String(this.bulkCompare).trim();
                }
            });
        },

        applyQuantityToBlank() {
            if (!this.canEdit || this.bulkQuantity === '' || this.bulkQuantity === null) {
                return;
            }

            this.includedRows.forEach((row) => {
                if (row.quantity === '' || row.quantity === null || typeof row.quantity === 'undefined') {
                    row.quantity = this.bulkQuantity;
                }
            });
        },
    }));
}
