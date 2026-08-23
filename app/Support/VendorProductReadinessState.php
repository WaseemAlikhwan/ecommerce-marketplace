<?php

namespace App\Support;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\ProductReadiness\ReadinessIssue;
use App\Support\ProductReadiness\ReadinessIssueMessages;
use App\Support\ProductReadiness\ReadinessResult;

final class VendorProductReadinessState
{
    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $visibilityWarnings
     * @param  array<string, string>  $actions
     * @param  array<string, string>  $labels
     */
    private function __construct(
        public readonly string $status,
        public readonly bool $isPublishable,
        public readonly int $publicationIssueCount,
        public readonly int $visibilityIssueCount,
        public readonly array $groups,
        public readonly array $visibilityWarnings,
        public readonly array $actions,
        public readonly bool $canPublish,
        public readonly bool $canUnpublish,
        public readonly bool $showPublishControl,
        public readonly bool $showUnpublishControl,
        public readonly ?string $publishedAt,
        public readonly ?string $publishedAtLabel,
        public readonly bool $topologyFrozen,
        public readonly string $storefrontEligibility,
        public readonly bool $formInitiallyDirty,
        public readonly bool $readOnlyLifecycle,
        public readonly string $lifecycleNotice,
        public readonly array $labels,
    ) {}

    /**
     * Presentation-only. Callers must supply authorization booleans; this class
     * must not invoke policies, relationships, or database queries.
     */
    public static function from(
        Product $product,
        ReadinessResult $result,
        bool $canAuthorizePublish,
        bool $canAuthorizeUnpublish,
        bool $formInitiallyDirty = false,
    ): self {
        $status = $product->status;
        $visibilityIssues = $result->visibilityIssues;
        $isPublishable = $result->isPublishable();

        $showPublishControl = in_array($status, [ProductStatus::Draft, ProductStatus::Unpublished], true)
            && $canAuthorizePublish;
        $showUnpublishControl = $status === ProductStatus::Published && $canAuthorizeUnpublish;

        $actions = [];
        if ($showPublishControl) {
            $actions['publish'] = route('vendor.products.publish', $product);
        }
        if ($showUnpublishControl) {
            $actions['unpublish'] = route('vendor.products.unpublish', $product);
        }

        $storefrontEligibility = match ($status) {
            ProductStatus::Published => $visibilityIssues === [] ? 'eligible' : 'hidden',
            default => 'n/a',
        };

        $readOnlyLifecycle = in_array($status, [ProductStatus::Suspended, ProductStatus::Archived], true);

        // Published: show integrity blockers in groups; contextual hide reasons only in visibilityWarnings.
        $groupSource = $status === ProductStatus::Published
            ? $result->integrityIssues
            : $result->publicationIssues();

        return new self(
            status: $status->value,
            isPublishable: $isPublishable,
            publicationIssueCount: count($result->publicationIssues()),
            visibilityIssueCount: count($visibilityIssues),
            groups: self::buildGroups($groupSource),
            visibilityWarnings: self::presentIssues($visibilityIssues),
            actions: $actions,
            canPublish: $showPublishControl && $isPublishable && ! $formInitiallyDirty,
            canUnpublish: $showUnpublishControl && ! $formInitiallyDirty,
            showPublishControl: $showPublishControl,
            showUnpublishControl: $showUnpublishControl,
            publishedAt: $product->published_at?->toIso8601String(),
            publishedAtLabel: $product->published_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i'),
            topologyFrozen: $product->published_at !== null && $product->type === ProductType::Variable,
            storefrontEligibility: $storefrontEligibility,
            formInitiallyDirty: $formInitiallyDirty,
            readOnlyLifecycle: $readOnlyLifecycle,
            lifecycleNotice: self::lifecycleNotice($status),
            labels: self::labels(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'status' => $this->status,
            'isPublishable' => $this->isPublishable,
            'publicationIssueCount' => $this->publicationIssueCount,
            'visibilityIssueCount' => $this->visibilityIssueCount,
            'groups' => $this->groups,
            'visibilityWarnings' => $this->visibilityWarnings,
            'actions' => $this->actions,
            'canPublish' => $this->canPublish,
            'canUnpublish' => $this->canUnpublish,
            'showPublishControl' => $this->showPublishControl,
            'showUnpublishControl' => $this->showUnpublishControl,
            'publishedAt' => $this->publishedAt,
            'publishedAtLabel' => $this->publishedAtLabel,
            'topologyFrozen' => $this->topologyFrozen,
            'storefrontEligibility' => $this->storefrontEligibility,
            'formInitiallyDirty' => $this->formInitiallyDirty,
            'readOnlyLifecycle' => $this->readOnlyLifecycle,
            'lifecycleNotice' => $this->lifecycleNotice,
            'labels' => $this->labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(): array
    {
        return $this->payload();
    }

    /**
     * @param  list<ReadinessIssue>  $issues
     * @return list<array<string, mixed>>
     */
    private static function buildGroups(array $issues): array
    {
        $definitions = [
            'content' => [
                'label' => __('Content'),
                'sections' => ['translations'],
            ],
            'classification' => [
                'label' => __('Product classification'),
                'sections' => ['category', 'brand', 'currency'],
            ],
            'gallery' => [
                'label' => __('Gallery'),
                'sections' => ['gallery'],
            ],
            'variants' => [
                'label' => __('Variants / matrix'),
                'sections' => ['variants', 'matrix'],
            ],
            'seller' => [
                'label' => __('Seller and store eligibility'),
                'sections' => ['vendor', 'store'],
            ],
        ];

        $bySection = [];
        foreach ($issues as $issue) {
            $bySection[$issue->section][] = $issue;
        }

        $groups = [];
        foreach ($definitions as $key => $definition) {
            $groupIssues = [];
            foreach ($definition['sections'] as $section) {
                foreach ($bySection[$section] ?? [] as $issue) {
                    $groupIssues[] = $issue;
                }
            }

            $presented = self::presentIssues($groupIssues);

            $groups[] = [
                'key' => $key,
                'label' => $definition['label'],
                'passed' => $presented === [],
                'issueCount' => array_sum(array_map(fn (array $row): int => $row['count'], $presented)),
                'issues' => $presented,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<ReadinessIssue>  $issues
     * @return list<array{code: string, message: string, count: int, anchor: string|null, section: string}>
     */
    private static function presentIssues(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            $key = $issue->code.'|'.$issue->section;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'code' => $issue->code,
                    'message' => ReadinessIssueMessages::messageFor($issue),
                    'count' => 0,
                    'anchor' => self::anchorFor($issue->section),
                    'section' => $issue->section,
                ];
            }
            $grouped[$key]['count']++;
        }

        return array_values($grouped);
    }

    private static function anchorFor(string $section): ?string
    {
        return match ($section) {
            'translations' => '#product-content',
            'category', 'brand', 'currency' => '#product-details',
            'gallery' => '#product-gallery',
            'variants' => '#product-variants',
            'matrix' => '#product-matrix',
            default => null,
        };
    }

    private static function lifecycleNotice(ProductStatus $status): string
    {
        return match ($status) {
            ProductStatus::Suspended => __('This product is suspended by moderation and cannot be published or unpublished by the vendor.'),
            ProductStatus::Archived => __('This product is archived. Publication actions are unavailable.'),
            default => '',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'panelTitle' => __('Publication readiness'),
            'readyTitle' => __('Ready to publish'),
            'readyDescription' => __('All publication requirements are satisfied for the saved product.'),
            'remainingTitle' => __('Remaining publication issues'),
            'issueCount' => __(':count remaining'),
            'passedGroup' => __('Complete'),
            'blockedGroup' => __('Needs attention'),
            'publish' => __('Publish'),
            'unpublish' => __('Unpublish'),
            'unpublishConfirm' => __('Unpublish this product? It will leave the catalog until you publish again.'),
            'saveFirst' => __('Save or discard changes first'),
            'publishedBadge' => __('Published'),
            'firstPublished' => __('First published'),
            'topologyFrozen' => __('Variable attribute topology is permanently frozen after first publication.'),
            'eligible' => __('Eligible for storefront'),
            'hidden' => __('Published but hidden by catalog rules'),
            'hiddenHint' => __('This does not mean the product is currently shown on the public storefront.'),
            'visibilityTitle' => __('Catalog visibility warnings'),
            'serverAuthoritative' => __('The server remains authoritative. Publishing while incomplete returns validation errors.'),
            'formDirty' => __('Unsaved product details'),
            'galleryDirty' => __('Unsaved gallery changes'),
            'galleryBusy' => __('Gallery upload in progress'),
            'reloadSaved' => __('Reload saved version'),
            'reloadConfirm' => __('Discard unsaved product details and reload the saved version?'),
        ];
    }
}
