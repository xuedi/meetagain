<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsEventTeaserExtension extends AbstractExtension
{
    public function __construct(
        private readonly EventFilterService $eventFilterService,
        private readonly EventRepository $eventRepository,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_upcoming_events', $this->getUpcomingEvents(...)),
        ];
    }

    /**
     * @return array<Event>
     */
    public function getUpcomingEvents(int $limit = 3): array
    {
        $eventIds = $this->eventFilterService->getEventIdFilter()->getEventIds();

        return $this->eventRepository->getUpcomingEvents($limit, $eventIds);
    }
}
