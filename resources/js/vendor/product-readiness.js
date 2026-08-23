export function registerVendorProductReadiness(Alpine) {
    Alpine.data('vendorProductReadiness', (config) => ({
        readiness: structuredClone(config || {}),
        labels: (config && config.labels) || {},
        productFormDirty: Boolean(config?.formInitiallyDirty),
        galleryOrderDirty: false,
        galleryAltDirty: false,
        galleryBusy: false,
        actionBusy: false,

        init() {
            this.$watch(
                () => this.productFormDirty,
                (value) => {
                    this.$el?.setAttribute('data-product-form-dirty', value ? '1' : '0');
                },
            );
            this.$watch(
                () => this.galleryBlocksPublication,
                (value) => {
                    this.$el?.setAttribute('data-gallery-blocks-publication', value ? '1' : '0');
                },
            );

            this.$el?.setAttribute('data-product-form-dirty', this.productFormDirty ? '1' : '0');
            this.$el?.setAttribute('data-gallery-blocks-publication', this.galleryBlocksPublication ? '1' : '0');

            window.addEventListener('vendor-product-form-dirty', (event) => {
                this.productFormDirty = Boolean(event.detail?.dirty);
            });

            window.addEventListener('vendor-product-gallery-state', (event) => {
                this.galleryOrderDirty = Boolean(event.detail?.orderDirty);
                this.galleryAltDirty = Boolean(event.detail?.altDirty);
                this.galleryBusy = Boolean(event.detail?.busy);
            });

            window.addEventListener('vendor-product-readiness-update', (event) => {
                if (event.detail?.readiness) {
                    this.applyReadiness(event.detail.readiness);
                }
            });
        },

        get localWorkDirty() {
            return this.productFormDirty || this.galleryBlocksPublication;
        },

        get galleryBlocksPublication() {
            return this.galleryOrderDirty || this.galleryAltDirty || this.galleryBusy;
        },

        get publishEnabled() {
            return Boolean(this.readiness?.canPublish)
                && Boolean(this.readiness?.showPublishControl)
                && !this.localWorkDirty
                && !this.actionBusy
                && !this.readiness?.readOnlyLifecycle;
        },

        get unpublishEnabled() {
            return Boolean(this.readiness?.canUnpublish)
                && Boolean(this.readiness?.showUnpublishControl)
                && !this.localWorkDirty
                && !this.actionBusy
                && !this.readiness?.readOnlyLifecycle;
        },

        get readinessState() {
            if (this.readiness?.readOnlyLifecycle) {
                return this.readiness.status || 'readonly';
            }

            if (this.readiness?.status === 'published') {
                return this.readiness.storefrontEligibility === 'hidden' ? 'published-hidden' : 'published';
            }

            return this.readiness?.isPublishable ? 'ready' : 'blocked';
        },

        applyReadiness(payload) {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            const next = structuredClone(payload);
            // Preserve current dirty flags; readiness is authoritative DB state only.
            next.formInitiallyDirty = this.productFormDirty;
            this.readiness = next;
            this.labels = next.labels || this.labels;
        },

        focusSection(anchor) {
            if (!anchor || typeof anchor !== 'string') {
                return;
            }

            const id = anchor.startsWith('#') ? anchor.slice(1) : anchor;
            const target = document.getElementById(id);
            if (!target) {
                return;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (typeof target.focus === 'function') {
                const previousTabIndex = target.getAttribute('tabindex');
                if (previousTabIndex === null) {
                    target.setAttribute('tabindex', '-1');
                }
                target.focus({ preventScroll: true });
                if (previousTabIndex === null) {
                    target.addEventListener(
                        'blur',
                        () => target.removeAttribute('tabindex'),
                        { once: true },
                    );
                }
            }
        },

        confirmUnpublish(event) {
            if (!this.unpublishEnabled) {
                event.preventDefault();
                return false;
            }

            const message = this.labels.unpublishConfirm
                || 'Unpublish this product? It will leave the catalog until you publish again.';

            if (!window.confirm(message)) {
                event.preventDefault();
                return false;
            }

            this.actionBusy = true;
            return true;
        },

        onPublishSubmit(event) {
            if (!this.publishEnabled) {
                event.preventDefault();
                return false;
            }

            this.actionBusy = true;
            return true;
        },
    }));
}
