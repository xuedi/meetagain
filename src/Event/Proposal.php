<?php declare(strict_types=1);

namespace App\Event;

use App\Enum\EventType;
use DateTimeImmutable;

final class Proposal
{
    public ?DateTimeImmutable $start = null;
    public ?DateTimeImmutable $stop = null;
    public ?EventType $type = null;
    public ?string $location = null;
    public string $locale = '';
    public string $title = '';
    public string $teaser = '';
    public string $description = '';
}
