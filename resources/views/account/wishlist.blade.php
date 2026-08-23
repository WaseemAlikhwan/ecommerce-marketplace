<x-app-layout :title="__('Wishlist')">
    <x-ui.page-header :title="__('Wishlist')" :description="__('Saved products will live here after the catalog ships.')" />

    <div class="grid gap-x-5 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
        <div class="border border-dashed border-line bg-surface/60 sm:col-span-2 lg:col-span-3">
            <x-ui.empty-state :title="__('Your wishlist is empty')" :action="__('Browse products')" :href="route('home')">
                {{ __('Wishlist actions currently toast a UI preview. Persistence arrives with commerce.') }}
            </x-ui.empty-state>
        </div>
    </div>
</x-app-layout>
