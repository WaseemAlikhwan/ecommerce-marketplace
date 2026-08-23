@props(['status'])

@if ($status)
    <x-ui.alert tone="success" {{ $attributes }}>
        {{ $status }}
    </x-ui.alert>
@endif
