<div
    class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex justify-center px-4 sm:inset-x-auto sm:end-4 sm:justify-end"
    aria-live="polite"
>
    <div class="flex w-full max-w-sm flex-col gap-2" x-data>
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="pointer-events-auto rounded-md border border-line bg-elevated px-4 py-3 shadow-md">
                <p class="text-sm text-ink" x-text="toast.message"></p>
            </div>
        </template>
    </div>
</div>
