<x-app-layout :title="__('Addresses')">
    <x-ui.page-header :title="__('Addresses')" :description="__('Manage your Syria delivery addresses for checkout.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Addresses')]]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('account.addresses.create')" variant="primary" type="button">{{ __('Add address') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($addresses === [])
        <div class="border border-dashed border-line bg-surface/60">
            <x-ui.empty-state :title="__('No addresses saved')" :action="__('Add address')" :href="route('account.addresses.create')">
                {{ __('Save a Syria governorate and city address for faster checkout.') }}
            </x-ui.empty-state>
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($addresses as $address)
                <li class="border border-line bg-surface p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium">{{ $address['label'] }} · {{ $address['recipient_name'] }}</p>
                                @if ($address['is_default'])
                                    <x-ui.badge tone="success">{{ __('Default') }}</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-ink-muted">{{ $address['summary'] }}</p>
                            <p class="mt-1 text-sm text-ink-muted">{{ $address['phone'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (! $address['is_default'])
                                <form method="POST" action="{{ route('account.addresses.default', $address['id']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Set as default') }}</x-ui.button>
                                </form>
                            @endif
                            <x-ui.button :href="route('account.addresses.edit', $address['id'])" variant="secondary" size="sm" type="button">{{ __('Edit') }}</x-ui.button>
                            <form method="POST" action="{{ route('account.addresses.destroy', $address['id']) }}" onsubmit="return confirm(@js(__('Delete this address?')))">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm">{{ __('Delete') }}</x-ui.button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-app-layout>
