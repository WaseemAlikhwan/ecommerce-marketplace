<x-ui.button {{ $attributes->merge(['variant' => 'danger', 'type' => $attributes->get('type', 'submit')]) }}>
    {{ $slot }}
</x-ui.button>
