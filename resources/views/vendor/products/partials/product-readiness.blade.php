@php
    /** @var array<string, mixed> $readinessBootstrap */
    $showPublish = (bool) ($readinessBootstrap['showPublishControl'] ?? false);
    $showUnpublish = (bool) ($readinessBootstrap['showUnpublishControl'] ?? false);
    $canPublishServer = (bool) ($readinessBootstrap['canPublish'] ?? false);
    $canUnpublishServer = (bool) ($readinessBootstrap['canUnpublish'] ?? false);
    $formInitiallyDirty = (bool) ($readinessBootstrap['formInitiallyDirty'] ?? false);
    $actions = $readinessBootstrap['actions'] ?? [];
    if ($actions instanceof \stdClass) {
        $actions = (array) $actions;
    }
    $publishUrl = is_array($actions) ? ($actions['publish'] ?? null) : null;
    $unpublishUrl = is_array($actions) ? ($actions['unpublish'] ?? null) : null;
    $labels = $readinessBootstrap['labels'] ?? [];
    $groups = $readinessBootstrap['groups'] ?? [];
    $visibilityWarnings = $readinessBootstrap['visibilityWarnings'] ?? [];
    $status = $readinessBootstrap['status'] ?? 'draft';
    $isPublishable = (bool) ($readinessBootstrap['isPublishable'] ?? false);
    $readOnlyLifecycle = (bool) ($readinessBootstrap['readOnlyLifecycle'] ?? false);
    $publishDisabled = ! $canPublishServer || $formInitiallyDirty;
    $unpublishDisabled = ! $canUnpublishServer || $formInitiallyDirty;
@endphp

<aside
    class="space-y-4 lg:sticky lg:top-24 lg:self-start"
    data-product-readiness
    data-readiness-state="{{ $status }}"
    :data-readiness-state="readinessState"
    data-product-form-dirty="{{ $formInitiallyDirty ? '1' : '0' }}"
    data-gallery-blocks-publication="0"
    aria-labelledby="product-readiness-heading"
>
    <x-ui.card>
        <div class="space-y-4">
            <div>
                <h2 id="product-readiness-heading" class="text-heading-3">{{ $labels['panelTitle'] ?? __('Publication readiness') }}</h2>
                <p class="mt-1 text-caption text-ink-muted">{{ $labels['serverAuthoritative'] ?? __('The server remains authoritative. Publishing while incomplete returns validation errors.') }}</p>
            </div>

            <noscript>
                <div class="space-y-3 rounded-sm border border-line bg-canvas/50 p-3 text-sm" data-readiness-noscript>
                    @if ($formInitiallyDirty)
                        <p class="flex items-start gap-2 text-warning">
                            <span aria-hidden="true">!</span>
                            <span>{{ $labels['saveFirst'] ?? __('Save or discard changes first') }}</span>
                        </p>
                    @endif

                    @if ($readOnlyLifecycle)
                        <p>{{ $readinessBootstrap['lifecycleNotice'] ?? '' }}</p>
                    @elseif ($status === 'published')
                        <p class="font-medium text-success">✓ {{ $labels['publishedBadge'] ?? __('Published') }}</p>
                        @if (! empty($readinessBootstrap['publishedAtLabel']))
                            <p class="text-caption text-ink-muted">
                                {{ $labels['firstPublished'] ?? __('First published') }}:
                                <time datetime="{{ $readinessBootstrap['publishedAt'] }}">{{ $readinessBootstrap['publishedAtLabel'] }}</time>
                            </p>
                        @endif
                        @if ($readinessBootstrap['topologyFrozen'] ?? false)
                            <p class="text-caption text-ink-muted">{{ $labels['topologyFrozen'] ?? '' }}</p>
                        @endif
                        @if (($readinessBootstrap['storefrontEligibility'] ?? '') === 'eligible')
                            <p class="text-success">✓ {{ $labels['eligible'] ?? __('Eligible for storefront') }}</p>
                        @elseif (($readinessBootstrap['storefrontEligibility'] ?? '') === 'hidden')
                            <p class="text-warning">! {{ $labels['hidden'] ?? __('Published but hidden by catalog rules') }}</p>
                            <p class="text-caption text-ink-muted">{{ $labels['hiddenHint'] ?? '' }}</p>
                            <ul class="list-disc ps-5">
                                @foreach ($visibilityWarnings as $warning)
                                    <li data-readiness-issue="{{ $warning['code'] }}">
                                        {{ $warning['message'] }}@if (($warning['count'] ?? 1) > 1) ({{ $warning['count'] }})@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif ($isPublishable)
                        <p class="text-success">✓ {{ $labels['readyTitle'] ?? __('Ready to publish') }}</p>
                    @else
                        <p class="text-warning">! {{ $labels['remainingTitle'] ?? __('Remaining publication issues') }}</p>
                        @foreach ($groups as $group)
                            @if (! ($group['passed'] ?? true))
                                <div>
                                    <p class="font-medium">{{ $group['label'] }}</p>
                                    <ul class="list-disc ps-5">
                                        @foreach ($group['issues'] ?? [] as $issue)
                                            <li data-readiness-issue="{{ $issue['code'] }}">
                                                @if (! empty($issue['anchor']))
                                                    <a href="{{ $issue['anchor'] }}">{{ $issue['message'] }}@if (($issue['count'] ?? 1) > 1) ({{ $issue['count'] }})@endif</a>
                                                @else
                                                    {{ $issue['message'] }}@if (($issue['count'] ?? 1) > 1) ({{ $issue['count'] }})@endif
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            </noscript>

            <div
                class="rounded-sm border border-line bg-canvas/50 p-3"
                role="status"
                aria-live="polite"
                data-readiness-live
                x-cloak
            >
                <template x-if="localWorkDirty">
                    <p class="flex items-start gap-2 text-sm text-warning">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-warning/40 text-caption" aria-hidden="true">!</span>
                        <span>
                            <span class="font-medium" x-text="labels.saveFirst"></span>
                            <span class="mt-1 block text-caption text-ink-muted" x-show="productFormDirty" x-text="labels.formDirty"></span>
                            <span class="mt-1 block text-caption text-ink-muted" x-show="galleryBlocksPublication" x-text="labels.galleryDirty"></span>
                        </span>
                    </p>
                </template>

                <template x-if="!localWorkDirty && readiness.readOnlyLifecycle">
                    <p class="flex items-start gap-2 text-sm text-ink">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-line text-caption" aria-hidden="true">i</span>
                        <span x-text="readiness.lifecycleNotice"></span>
                    </p>
                </template>

                <template x-if="!localWorkDirty && !readiness.readOnlyLifecycle && readiness.status === 'published'">
                    <div class="space-y-2 text-sm">
                        <p class="flex items-center gap-2 font-medium text-success">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-success/40 text-caption" aria-hidden="true">✓</span>
                            <span x-text="labels.publishedBadge"></span>
                        </p>
                        <p class="text-caption text-ink-muted" x-show="readiness.publishedAtLabel">
                            <span x-text="labels.firstPublished"></span>:
                            <time dir="ltr" :datetime="readiness.publishedAt" x-text="readiness.publishedAtLabel"></time>
                        </p>
                        <p class="text-caption text-ink-muted" x-show="readiness.topologyFrozen" x-text="labels.topologyFrozen"></p>
                        <p class="flex items-start gap-2 text-success" x-show="readiness.storefrontEligibility === 'eligible'">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-success/40 text-caption" aria-hidden="true">✓</span>
                            <span>
                                <span class="font-medium" x-text="labels.eligible"></span>
                                <span class="mt-1 block text-caption text-ink-muted" x-text="labels.hiddenHint"></span>
                            </span>
                        </p>
                        <div x-show="readiness.storefrontEligibility === 'hidden'" class="space-y-2">
                            <p class="flex items-start gap-2 text-warning">
                                <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-warning/40 text-caption" aria-hidden="true">!</span>
                                <span>
                                    <span class="font-medium" x-text="labels.hidden"></span>
                                    <span class="mt-1 block text-caption text-ink-muted" x-text="labels.hiddenHint"></span>
                                </span>
                            </p>
                            <p class="text-caption font-medium text-ink" x-text="labels.visibilityTitle"></p>
                            <ul class="space-y-1">
                                <template x-for="warning in (readiness.visibilityWarnings || [])" :key="warning.code + '-' + warning.section">
                                    <li>
                                        <button
                                            type="button"
                                            class="text-start text-caption text-brand underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                            data-readiness-issue
                                            :data-readiness-issue="warning.code"
                                            x-show="warning.anchor"
                                            x-on:click="focusSection(warning.anchor)"
                                            x-text="warning.count > 1 ? `${warning.message} (${warning.count})` : warning.message"
                                        ></button>
                                        <span
                                            class="text-caption text-ink"
                                            data-readiness-issue
                                            :data-readiness-issue="warning.code"
                                            x-show="!warning.anchor"
                                            x-text="warning.count > 1 ? `${warning.message} (${warning.count})` : warning.message"
                                        ></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>

                <template x-if="!localWorkDirty && !readiness.readOnlyLifecycle && readiness.status !== 'published' && readiness.isPublishable">
                    <p class="flex items-start gap-2 text-sm text-success">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-success/40 text-caption" aria-hidden="true">✓</span>
                        <span>
                            <span class="font-medium" x-text="labels.readyTitle"></span>
                            <span class="mt-1 block text-caption text-ink-muted" x-text="labels.readyDescription"></span>
                        </span>
                    </p>
                </template>

                <template x-if="!localWorkDirty && !readiness.readOnlyLifecycle && readiness.status !== 'published' && !readiness.isPublishable">
                    <p class="flex items-start gap-2 text-sm text-warning">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-warning/40 text-caption" aria-hidden="true">!</span>
                        <span>
                            <span class="font-medium" x-text="labels.remainingTitle"></span>
                            <span class="mt-1 block text-caption text-ink-muted" x-text="(labels.issueCount || '').replace(':count', String(readiness.publicationIssueCount || 0))"></span>
                        </span>
                    </p>
                </template>
            </div>

            @unless ($readOnlyLifecycle)
                <div
                    class="space-y-3"
                    x-show="readiness.status !== 'published'"
                    x-cloak
                >
                    <template x-for="group in (readiness.groups || [])" :key="group.key">
                        <section class="rounded-sm border border-line p-3" :data-readiness-group="group.key">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-medium text-ink" x-text="group.label"></h3>
                                <span
                                    class="inline-flex items-center gap-1 text-caption"
                                    :class="group.passed ? 'text-success' : 'text-warning'"
                                >
                                    <span aria-hidden="true" x-text="group.passed ? '✓' : '!'"></span>
                                    <span x-text="group.passed ? labels.passedGroup : labels.blockedGroup"></span>
                                </span>
                            </div>
                            <ul class="mt-2 space-y-1" x-show="!group.passed">
                                <template x-for="issue in group.issues" :key="issue.code + '-' + issue.section">
                                    <li>
                                        <button
                                            type="button"
                                            class="text-start text-caption text-brand underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                            data-readiness-issue
                                            :data-readiness-issue="issue.code"
                                            x-show="issue.anchor"
                                            x-on:click="focusSection(issue.anchor)"
                                            x-text="issue.count > 1 ? `${issue.message} (${issue.count})` : issue.message"
                                        ></button>
                                        <span
                                            class="text-caption text-ink"
                                            data-readiness-issue
                                            :data-readiness-issue="issue.code"
                                            x-show="!issue.anchor"
                                            x-text="issue.count > 1 ? `${issue.message} (${issue.count})` : issue.message"
                                        ></span>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </template>
                </div>
            @endunless

            <div class="flex flex-col gap-2">
                @if ($showPublish && $publishUrl)
                    <form
                        method="POST"
                        action="{{ $publishUrl }}"
                        data-publish-action
                        x-on:submit="onPublishSubmit($event)"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            class="w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                            :disabled="$publishDisabled"
                            x-bind:disabled="!publishEnabled"
                        >
                            {{ $labels['publish'] ?? __('Publish') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($showUnpublish && $unpublishUrl)
                    <form
                        method="POST"
                        action="{{ $unpublishUrl }}"
                        data-unpublish-action
                        x-on:submit="confirmUnpublish($event)"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="secondary"
                            class="w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                            :disabled="$unpublishDisabled"
                            x-bind:disabled="!unpublishEnabled"
                        >
                            {{ $labels['unpublish'] ?? __('Unpublish') }}
                        </x-ui.button>
                    </form>
                @endif
            </div>
        </div>
    </x-ui.card>
</aside>
