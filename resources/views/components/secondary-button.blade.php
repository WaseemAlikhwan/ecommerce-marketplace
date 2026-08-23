<x-ui.button {{ $attributes->merge(['variant' => 'secondary', 'type' => $attributes->get('type', 'button')]) }}>
    {{ $slot }}
</x-ui.button>
