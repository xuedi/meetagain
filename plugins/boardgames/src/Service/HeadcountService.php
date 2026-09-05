<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Event;
use App\Repository\RsvpGuestRepository;

readonly class HeadcountService
{
    public function __construct(
        private RsvpGuestRepository $rsvpGuests,
    ) {}

    public function forEvent(Event $event): int
    {
        $members = $event->getRsvp()->count();
        $guests = array_sum($this->rsvpGuests->getCountsForEvent($event));

        return $members + $guests + $event->getExternalRsvp();
    }
}
