<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Entity\Event;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Service\Config\PluginService;
use App\Service\Email\EmailService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class EventService
{
    public function __construct(
        private EventRepository $repo,
        private EntityManagerInterface $em,
        private EmailService $emailService,
        private PluginService $pluginService,
        private RecurringEventService $recurringEventService,
        #[AutowireIterator(Plugin::class)]
        private iterable $plugins,
    ) {}

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     */
    public function getFilteredList(
        EventTimeFilter $time,
        EventSortFilter $sort,
        EventType $type,
        EventRsvpFilter $rsvp,
        ?UserInterface $user = null,
        ?array $restrictToEventIds = null,
    ): array {
        $result = $this->repo->findByFilters($time, $sort, $type, $user, $rsvp, $restrictToEventIds);

        return $this->structureList($result);
    }

    public function updateRecurringEvents(Event $event): int
    {
        return $this->recurringEventService->updateRecurringEvents($event);
    }

    public function cancelEvent(Event $event): void
    {
        $event->setCanceled(true);
        $this->em->persist($event);
        $this->em->flush();

        if ($event->getStart() > new DateTime()) {
            foreach ($event->getRsvp() as $user) {
                $this->emailService->prepareEventCanceledNotification($user, $event);
            }
            $this->emailService->sendQueue();
        }
    }

    public function uncancelEvent(Event $event): void
    {
        $event->setCanceled(false);
        $this->em->persist($event);
        $this->em->flush();
    }

    private function structureList(array $events): array
    {
        $structuredList = [];
        foreach ($events as $event) {
            $key = $event->getStart()->format('Y-m');
            if (!isset($structuredList[$key])) {
                $structuredList[$key] = [
                    'year' => $event->getStart()->format('Y'),
                    'month' => $event->getStart()->format('F'),
                    'events' => [],
                ];
            }
            $structuredList[$key]['events'][] = $event;
        }

        return $structuredList;
    }

    public function getPluginEventTiles(int $id): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $tiles = [];
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            $tile = $plugin->getEventTile($id);
            if ($tile !== null) {
                $tiles[] = $tile;
            }
        }

        return $tiles;
    }
}
