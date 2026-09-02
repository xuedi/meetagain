<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\RsvpGuestRepository;
use Twig\Extension\RuntimeExtensionInterface;

final class RsvpGuestRuntime implements RuntimeExtensionInterface
{
    /** @var array<int, array<int, int>> */
    private array $countsByEvent = [];

    public function __construct(
        private readonly RsvpGuestRepository $repo,
    ) {}

    public function getGuestCount(Event $event, User $user): int
    {
        $eventId = (int) $event->getId();
        $this->countsByEvent[$eventId] ??= $this->repo->getCountsForEvent($event);

        return $this->countsByEvent[$eventId][(int) $user->getId()] ?? 0;
    }
}
