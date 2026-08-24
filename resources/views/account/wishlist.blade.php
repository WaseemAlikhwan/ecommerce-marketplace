<x-app-layout :title="__('Wishlist')">
    <x-ui.page-header :title="__('Wishlist')" :description="__('Saved products.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Wishlist')],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($products === [])
        <div class="grid gap-x-5 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border border-dashed border-line bg-surface/60 sm:col-span-2 lg:col-span-3">
                <x-ui.empty-state :title="__('Your wishlist is empty')" :action="__('Browse products')" :href="route('storefront.search')">
                    {{ __('Browse the catalog and save products you like.') }}
                </x-ui.empty-state>
            </div>
        </div>
    @else
        <div class="grid gap-x-5 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $row)
                <div>
                    <x-commerce.product-card :product="$row['card']" />
                    <form method="POST" action="{{ route('account.wishlist.destroy', $row['wishlist_item_id']) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="ghost" size="sm" class="w-full">
                            {{ __('Remove from wishlist') }}
                        </x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
