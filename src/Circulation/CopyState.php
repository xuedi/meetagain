<?php declare(strict_types=1);

namespace App\Circulation;

use App\Enum\CirculationCopyStatus;
use DateTimeImmutable;

final readonly class CopyState
{
    public function __construct(
        public ?int $holderId,
        public ?DateTimeImmutable $heldSince,
        public CirculationCopyStatus $status,
    ) {}

    public function with(?int $holderId = null, ?DateTimeImmutable $heldSince = null, ?CirculationCopyStatus $status = null): self
    {
        return new self(
            $holderId ?? $this->holderId,
            $heldSince ?? $this->heldSince,
            $status ?? $this->status,
        );
    }

    public function equals(?int $holderId, ?DateTimeImmutable $heldSince, CirculationCopyStatus $status): bool
    {
        $sameMoment = $this->heldSince?->getTimestamp() === $heldSince?->getTimestamp();

        return $this->holderId === $holderId && $sameMoment && $this->status === $status;
    }
}
