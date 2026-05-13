<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use Plugin\Filmclub\Entity\ExternalSource;

readonly class FilmMetadata
{
    /** @param string[] $genres */
    public function __construct(
        public string $externalId,
        public ExternalSource $source,
        public string $title,
        public ?string $originalTitle = null,
        public ?string $description = null,
        public ?int $year = null,
        public ?int $runtime = null,
        public array $genres = [],
        public ?string $posterUrl = null,
    ) {}
}
