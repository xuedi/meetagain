<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\RsvpGuestRepository;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RsvpGuestExtension extends AbstractExtension
{
    /** @var array<int, array<int, int>> */
    private array $countsByEvent = [];

    public function __construct(
        private readonly RsvpGuestRepository $repo,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('rsvp_guest_count', $this->getGuestCount(...)),
        ];
    }

    public function getGuestCount(Event $event, User $user): int
    {
        $eventId = (int) $event->getId();
        $this->countsByEvent[$eventId] ??= $this->repo->getCountsForEvent($event);

        return $this->countsByEvent[$eventId][(int) $user->getId()] ?? 0;
    }
}
