<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Cms;

readonly class MenuItem
{
    public function __construct(
        public string $slug,
        public string $name,
        public float $priority,
    ) {}

    public static function fromCms(Cms $cms, string $locale): self
    {
        return new self(
            slug: '/' . $locale . '/' . $cms->getSlug(),
            name: $cms->getLinkName($locale) ?? $cms->getSlug() ?? '',
            priority: 0.0,
        );
    }
}
