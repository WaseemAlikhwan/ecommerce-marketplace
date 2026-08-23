<x-ui.button {{ $attributes->merge(['variant' => 'primary', 'type' => $attributes->get('type', 'submit')]) }}>
    {{ $slot }}
</x-ui.button>
