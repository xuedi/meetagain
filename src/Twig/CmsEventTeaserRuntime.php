<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CmsEventTeaserRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EventFilterService $eventFilterService,
        private EventRepository $eventRepository,
    ) {}

    /**
     * @return array<Event>
     */
    public function getUpcomingEvents(int $limit = 3): array
    {
        $eventIds = $this->eventFilterService->getEventIdFilter()->getEventIds();

        return $this->eventRepository->getUpcomingEvents($limit, $eventIds);
    }
}
