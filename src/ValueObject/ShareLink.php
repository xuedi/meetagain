<?php declare(strict_types=1);

namespace App\ValueObject;

final readonly class ShareLink
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public string $icon = '',
    ) {}
}
