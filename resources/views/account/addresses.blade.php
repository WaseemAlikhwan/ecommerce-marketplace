<x-app-layout :title="__('Addresses')">
    <x-ui.page-header :title="__('Addresses')" :description="__('Delivery addresses will be managed here in a later phase.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" type="button" disabled>{{ __('Add address') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="border border-line bg-surface">
        <x-ui.empty-state :title="__('No addresses saved')">
            {{ __('Address forms and maps are deferred until shipping is implemented.') }}
        </x-ui.empty-state>
    </div>
</x-app-layout>
