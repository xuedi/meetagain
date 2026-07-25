<?php declare(strict_types=1);

namespace App\ValueObject;

/**
 * Outcome of one rebuild pass over one locale of one series.
 */
final readonly class CanonicalRebuildSummary
{
    public function __construct(
        public string $locale,
        public int $membersScanned,
        public int $rootsWritten,
        public int $detachedWritten,
        public int $markersRemoved,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            locale: $this->locale,
            membersScanned: $this->membersScanned + $other->membersScanned,
            rootsWritten: $this->rootsWritten + $other->rootsWritten,
            detachedWritten: $this->detachedWritten + $other->detachedWritten,
            markersRemoved: $this->markersRemoved + $other->markersRemoved,
        );
    }
}
