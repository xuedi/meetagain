<?php declare(strict_types=1);

namespace App\Localization;

final readonly class LocalizedContentRow
{
    public function __construct(
        public string $sourceKey,
        public int $ownerId,
        public string $locale,
        public string $ownerLabel,
        public string $preview,
    ) {}
}
