<?php

namespace App\Support;

final class NormalizedProductImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
        public readonly int $sizeBytes,
    ) {}
}
