<?php

namespace App\Support\ProductReadiness;

final readonly class ReadinessResult
{
    /**
     * @param  list<ReadinessIssue>  $integrityIssues
     * @param  list<ReadinessIssue>  $publicationDependencyIssues
     * @param  list<ReadinessIssue>  $visibilityIssues
     */
    public function __construct(
        public array $integrityIssues,
        public array $publicationDependencyIssues,
        public array $visibilityIssues,
    ) {}

    /**
     * @return list<ReadinessIssue>
     */
    public function publicationIssues(): array
    {
        return [...$this->integrityIssues, ...$this->publicationDependencyIssues];
    }

    public function isPublishable(): bool
    {
        return $this->publicationIssues() === [];
    }

    public function hasIntegrityIssues(): bool
    {
        return $this->integrityIssues !== [];
    }
}
