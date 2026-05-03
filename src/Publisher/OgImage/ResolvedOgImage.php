<?php declare(strict_types=1);

namespace App\Publisher\OgImage;

final readonly class ResolvedOgImage
{
    public function __construct(
        public string $absoluteUrl,
        public int $width,
        public int $height,
        public string $altText,
    ) {}
}
