<?php

namespace App\Support\ProductReadiness;

final readonly class ReadinessIssue
{
    /**
     * @param  array<string, int|string|bool|null>  $meta
     */
    public function __construct(
        public string $code,
        public string $section,
        public array $meta = [],
    ) {}
}
