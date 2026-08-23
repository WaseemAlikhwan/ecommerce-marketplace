const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function registerStorefrontDialog(Alpine) {
    Alpine.data('storefrontDialog', () => ({
        open: false,
        returnTarget: null,

        showDialog() {
            this.returnTarget = document.activeElement;
            this.open = true;
            this.setBackgroundBlocked(true);

            this.$nextTick(() => {
                const focusable = this.focusableElements();
                (focusable[0] || this.$refs.dialog)?.focus();
            });
        },

        closeDialog(restoreFocus = true) {
            if (!this.open) {
                return;
            }

            this.open = false;
            this.setBackgroundBlocked(false);

            if (restoreFocus) {
                this.$nextTick(() => this.returnTarget?.focus?.());
            }
        },

        handleDialogKeydown(event) {
            if (!this.open) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeDialog();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = this.focusableElements();
            if (focusable.length === 0) {
                event.preventDefault();
                this.$refs.dialog?.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        focusableElements() {
            return Array.from(
                this.$refs.dialog?.querySelectorAll(FOCUSABLE) || [],
            ).filter(
                (element) =>
                    !element.hidden &&
                    element.getAttribute('aria-hidden') !== 'true' &&
                    element.getClientRects().length > 0,
            );
        },

        setBackgroundBlocked(blocked) {
            document.documentElement.classList.toggle(
                'overflow-hidden',
                blocked,
            );

            document
                .querySelectorAll('[data-storefront-background]')
                .forEach((element) => {
                    if (blocked) {
                        element.setAttribute('inert', '');
                    } else {
                        element.removeAttribute('inert');
                    }
                });
        },

        destroy() {
            if (this.open) {
                this.setBackgroundBlocked(false);
            }
        },
    }));
}
