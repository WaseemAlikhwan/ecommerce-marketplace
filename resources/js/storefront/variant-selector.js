export function registerStorefrontVariantSelector(Alpine) {
    Alpine.data('storefrontVariantSelector', (config = {}) => ({
        attributes: Array.isArray(config.attributes) ? config.attributes : [],
        variants: Array.isArray(config.variants) ? config.variants : [],
        defaultVariant: config.defaultVariant || null,
        priceRangeLabel: String(config.priceRangeLabel || ''),
        messages: {
            incomplete: String(config.messages?.incomplete || ''),
            unavailable: String(config.messages?.unavailable || ''),
            in_stock: String(config.messages?.inStock || ''),
            out_of_stock: String(config.messages?.outOfStock || ''),
        },
        selected: {},

        init() {
            const selection = this.defaultVariant?.selection || [];

            this.selected = Object.fromEntries(
                selection
                    .filter((item) =>
                        this.attributes.some(
                            (attribute) =>
                                String(attribute.code) ===
                                String(item.attribute_code),
                        ),
                    )
                    .map((item) => [
                        String(item.attribute_code),
                        String(item.value_code),
                    ]),
            );
        },

        select(attributeCode, valueCode) {
            const code = String(attributeCode);
            const value = String(valueCode);
            const index = this.attributeIndex(code);

            if (index < 0 || !this.isValueAvailable(code, value)) {
                return;
            }

            const next = { ...this.selected, [code]: value };

            for (let later = index + 1; later < this.attributes.length; later++) {
                const laterCode = String(this.attributes[later].code);
                const laterValue = next[laterCode];

                if (
                    laterValue &&
                    !this.valueAvailableForSelection(later, laterValue, next)
                ) {
                    delete next[laterCode];
                }
            }

            this.selected = next;
        },

        isSelected(attributeCode, valueCode) {
            return (
                this.selected[String(attributeCode)] === String(valueCode)
            );
        },

        attributeIndex(attributeCode) {
            return this.attributes.findIndex(
                (attribute) =>
                    String(attribute.code) === String(attributeCode),
            );
        },

        variantSelection(variant) {
            return Object.fromEntries(
                (variant?.selection || []).map((item) => [
                    String(item.attribute_code),
                    String(item.value_code),
                ]),
            );
        },

        valueAvailableForSelection(index, valueCode, selection) {
            const attribute = this.attributes[index];

            if (!attribute) {
                return false;
            }

            const currentCode = String(attribute.code);
            const candidate = String(valueCode);

            return this.variants.some((variant) => {
                const values = this.variantSelection(variant);

                if (values[currentCode] !== candidate) {
                    return false;
                }

                return this.attributes
                    .slice(0, index)
                    .every((preceding) => {
                        const code = String(preceding.code);
                        const selectedValue = selection[code];

                        return (
                            !selectedValue ||
                            values[code] === String(selectedValue)
                        );
                    });
            });
        },

        isValueAvailable(attributeCode, valueCode) {
            const index = this.attributeIndex(attributeCode);

            return (
                index >= 0 &&
                this.valueAvailableForSelection(
                    index,
                    valueCode,
                    this.selected,
                )
            );
        },

        selectedValueLabel(attributeCode) {
            const code = String(attributeCode);
            const selectedValue = this.selected[code];
            const attribute = this.attributes.find(
                (item) => String(item.code) === code,
            );
            const value = (attribute?.values || []).find(
                (item) => String(item.code) === String(selectedValue),
            );

            return value?.name ? String(value.name) : '';
        },

        get isComplete() {
            return this.attributes.every(
                (attribute) => this.selected[String(attribute.code)],
            );
        },

        get selectedVariant() {
            if (!this.isComplete) {
                return null;
            }

            return (
                this.variants.find((variant) => {
                    const values = this.variantSelection(variant);

                    return this.attributes.every(
                        (attribute) =>
                            values[String(attribute.code)] ===
                            this.selected[String(attribute.code)],
                    );
                }) || null
            );
        },

        get state() {
            if (!this.isComplete) {
                return 'incomplete';
            }

            if (!this.selectedVariant) {
                return 'unavailable';
            }

            return this.selectedVariant.in_stock
                ? 'in_stock'
                : 'out_of_stock';
        },

        get statusLabel() {
            return this.messages[this.state] || '';
        },

        get selectedPriceLabel() {
            return (
                this.selectedVariant?.price_label || this.priceRangeLabel || ''
            );
        },

        get selectedCompareAtLabel() {
            return this.selectedVariant?.compare_at_label || '';
        },

        get selectedInStock() {
            return this.state === 'in_stock';
        },
    }));
}
