<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProductImageProcessor
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MIN_DIMENSION = 400;

    public const MAX_DIMENSION = 6000;

    public const MAX_PIXELS = 16_000_000;

    /**
     * @var array<string, string>
     */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function normalize(UploadedFile $file): NormalizedProductImage
    {
        $this->assertWebpSupport();

        if (! $file->isValid() || $file->getSize() === false) {
            throw ValidationException::withMessages([
                'image' => __('The uploaded image is invalid.'),
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => __('The image may not be larger than 5 MiB.'),
            ]);
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw ValidationException::withMessages([
                'image' => __('The uploaded image is invalid.'),
            ]);
        }

        $mime = $this->detectMime($path);
        $this->assertAllowedMime($mime);

        $info = @getimagesize($path);
        if ($info === false || ! isset($info[0], $info[1], $info['mime'])) {
            throw ValidationException::withMessages([
                'image' => __('The uploaded file is not a valid image.'),
            ]);
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $reportedMime = strtolower((string) $info['mime']);
        if ($reportedMime === 'image/jpg') {
            $reportedMime = 'image/jpeg';
        }

        if ($reportedMime !== $mime) {
            throw ValidationException::withMessages([
                'image' => __('The image content does not match its type.'),
            ]);
        }

        $this->assertDimensions($width, $height);

        $image = $this->decode($path, $mime);

        try {
            if ($mime === 'image/jpeg') {
                $image = $this->applyJpegOrientation($image, $path);
            }

            $encoded = $this->encode($image, $mime);
        } finally {
            if ($image instanceof GdImage) {
                imagedestroy($image);
            }
        }

        $size = strlen($encoded);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => __('The normalized image may not be larger than 5 MiB.'),
            ]);
        }

        $verified = @getimagesizefromstring($encoded);
        if ($verified === false || ! isset($verified[0], $verified[1])) {
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        return new NormalizedProductImage(
            bytes: $encoded,
            mimeType: $mime,
            extension: self::MIME_EXTENSIONS[$mime],
            width: (int) $verified[0],
            height: (int) $verified[1],
            sizeBytes: $size,
        );
    }

    public function assertWebpSupport(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromwebp')) {
            throw new \RuntimeException('GD WebP support is required for product images.');
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($path));
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        return $mime;
    }

    private function assertAllowedMime(string $mime): void
    {
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw ValidationException::withMessages([
                'image' => __('Only JPEG, PNG, and WebP images are allowed.'),
            ]);
        }
    }

    private function assertDimensions(int $width, int $height): void
    {
        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw ValidationException::withMessages([
                'image' => __('Images must be at least 400×400 pixels.'),
            ]);
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages([
                'image' => __('Images may not exceed 6000 pixels in width or height.'),
            ]);
        }

        if (($width * $height) > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                'image' => __('Images may not exceed 16 megapixels.'),
            ]);
        }
    }

    private function decode(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        if (! imageistruecolor($image) && ! imagepalettetotruecolor($image)) {
            imagedestroy($image);
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function applyJpegOrientation(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 270),
            6 => $this->rotate($image, 270),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            imagedestroy($image);
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        return $image;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);
        if (! $rotated instanceof GdImage) {
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    private function encode(GdImage $image, string $mime): string
    {
        $level = ob_get_level();
        ob_start();

        $ok = false;
        $bytes = '';

        try {
            $ok = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, 85),
                'image/png' => imagepng($image, null, 6),
                'image/webp' => imagewebp($image, null, 80),
                default => false,
            };
            $bytes = (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        if ($ok !== true || $bytes === '') {
            throw ValidationException::withMessages([
                'image' => __('The image could not be processed.'),
            ]);
        }

        return $bytes;
    }
}
