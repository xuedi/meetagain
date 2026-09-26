<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use DateTimeImmutable;
use Module\Circulation\Contract\CopyStatus;

final readonly class CopyState
{
    public function __construct(
        public ?int $holderId,
        public ?DateTimeImmutable $heldSince,
        public CopyStatus $status,
    ) {}

    public function with(?int $holderId = null, ?DateTimeImmutable $heldSince = null, ?CopyStatus $status = null): self
    {
        return new self($holderId ?? $this->holderId, $heldSince ?? $this->heldSince, $status ?? $this->status);
    }

    public function equals(?int $holderId, ?DateTimeImmutable $heldSince, CopyStatus $status): bool
    {
        $sameMoment = $this->heldSince?->getTimestamp() === $heldSince?->getTimestamp();

        return $this->holderId === $holderId && $sameMoment && $this->status === $status;
    }
}
