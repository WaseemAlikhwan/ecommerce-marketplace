import Alpine from 'alpinejs';
import { registerStorefrontDialog } from './storefront/dialog';
import { registerStorefrontVariantSelector } from './storefront/variant-selector';
import { registerVendorProductForm } from './vendor/product-form';
import { registerVendorProductGallery } from './vendor/product-gallery';
import { registerVendorProductReadiness } from './vendor/product-readiness';

document.addEventListener('alpine:init', () => {
    registerStorefrontDialog(Alpine);
    registerStorefrontVariantSelector(Alpine);
    registerVendorProductForm(Alpine);
    registerVendorProductGallery(Alpine);
    registerVendorProductReadiness(Alpine);

    Alpine.store('toasts', {
        items: [],
        push(message) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message });
            window.setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });
});

window.Alpine = Alpine;

Alpine.start();
