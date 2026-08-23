@props([
    'columns' => [],
    'rows' => [],
])

<div {{ $attributes->class('ds-table-wrap') }}>
    <table class="ds-table">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th scope="col">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="px-4 py-10">
                        {{ $empty ?? '' }}
                        @unless (isset($empty))
                            <x-ui.empty-state :title="__('No records yet')">
                                {{ __('This table is a visual foundation. Live records will appear in a later phase.') }}
                            </x-ui.empty-state>
                        @endunless
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $footer ?? '' }}
</div>
