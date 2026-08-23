@if (is_array($merge) && (($merge['adjusted'] ?? []) !== [] || ($merge['unavailable'] ?? []) !== []))
    <div class="mt-6 space-y-3">
        @if (($merge['adjusted'] ?? []) !== [])
            <x-ui.alert tone="warning" :title="__('Some quantities were updated')">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($merge['adjusted'] as $row)
                        <li>
                            {{ __('A line quantity changed from :from to :to to match available stock.', [
                                'from' => (int) ($row['from_quantity'] ?? 0),
                                'to' => (int) ($row['to_quantity'] ?? 0),
                            ]) }}
                        </li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        @if (($merge['unavailable'] ?? []) !== [])
            <x-ui.alert tone="danger" :title="__('Some items could not be kept')">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($merge['unavailable'] as $row)
                        <li>
                            @php($reason = (string) ($row['reason'] ?? ''))
                            {{ match ($reason) {
                                \App\Cart\CartMergeUnavailable::OUT_OF_STOCK => __('An item was removed because it is out of stock.'),
                                default => __('An item was removed because it is no longer available.'),
                            } }}
                        </li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif
    </div>
@endif
