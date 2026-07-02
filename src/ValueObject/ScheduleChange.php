<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\EventInterval;
use DateTimeImmutable;

final readonly class ScheduleChange
{
    public function __construct(
        public DateTimeImmutable $oldStart,
        public ?DateTimeImmutable $oldStop,
        public ?EventInterval $oldRule,
        public DateTimeImmutable $newStart,
        public ?DateTimeImmutable $newStop,
        public ?EventInterval $newRule,
    ) {}

    public function isChanged(): bool
    {
        if ($this->oldStart->getTimestamp() !== $this->newStart->getTimestamp()) {
            return true;
        }
        if ($this->oldStop?->getTimestamp() !== $this->newStop?->getTimestamp()) {
            return true;
        }

        return $this->oldRule !== $this->newRule;
    }
}
