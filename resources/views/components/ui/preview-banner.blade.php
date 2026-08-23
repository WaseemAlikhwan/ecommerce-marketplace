<x-ui.alert tone="info" class="mb-6" :title="__('UI preview')">
    {{ $slot->isEmpty() ? __('This screen is a visual foundation only. It does not show live marketplace data.') : $slot }}
</x-ui.alert>
