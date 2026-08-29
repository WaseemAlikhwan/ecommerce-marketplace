<x-app-layout :title="__('Edit address')">
    <x-ui.page-header :title="__('Edit address')" :description="__('Update your Syria delivery address.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Addresses'), 'href' => route('account.addresses')],
                ['label' => __('Edit address')],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="max-w-2xl border border-line bg-surface p-6">
        @csrf
        @method('PUT')
        @include('account.addresses._form', ['address' => $address, 'governorates' => $governorates])
        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button type="submit" variant="primary">{{ __('Save address') }}</x-ui.button>
            <x-ui.button :href="route('account.addresses')" variant="secondary" type="button">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-app-layout>
