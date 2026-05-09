<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

final readonly class IncidentSourceStats
{
    public function __construct(
        public string $sourceKey,
        public int $considered,
        public int $ipsTouched,
        public int $incidentsTouched,
        public int $lastProcessedId,
        public bool $hasMore,
    ) {}

    public static function empty(string $sourceKey, int $lastProcessedId): self
    {
        return new self($sourceKey, 0, 0, 0, $lastProcessedId, false);
    }
}
