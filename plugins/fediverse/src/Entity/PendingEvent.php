<?php declare(strict_types=1);

namespace Plugin\Fediverse\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Fediverse\Repository\PendingEventRepository;

#[ORM\Entity(repositoryClass: PendingEventRepository::class)]
#[ORM\Table(name: 'plg_fediverse_pending_event')]
class PendingEvent
{
    #[ORM\Id, ORM\Column]
    private int $eventId;

    #[ORM\Column]
    private DateTimeImmutable $queuedAt;

    public function __construct(int $eventId, DateTimeImmutable $queuedAt)
    {
        $this->eventId = $eventId;
        $this->queuedAt = $queuedAt;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function getQueuedAt(): DateTimeImmutable
    {
        return $this->queuedAt;
    }
}
