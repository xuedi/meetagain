<?php declare(strict_types=1);

namespace App\ValueObject;

final readonly class SitemapRow
{
    /**
     * @param list<string> $warnings translation keys
     */
    public function __construct(
        public string $section,
        public string $label,
        public string $url,
        public string $locale,
        public string $lastmod,
        public array $warnings,
    ) {}

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
