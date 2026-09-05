<?php declare(strict_types=1);

namespace Plugin\Photos\DataHotfix;

use App\DataHotfix\DataHotfixInterface;
use App\Entity\Event;
use App\Repository\EventItemAssociationRepository;
use App\Repository\EventRepository;
use App\Service\Event\EventScope;
use Override;
use Plugin\Photos\Event\DateTagService;
use Plugin\Photos\Service\PhotoService;

readonly class BackfillEventDateTags implements DataHotfixInterface
{
    public function __construct(
        private EventItemAssociationRepository $associationRepo,
        private EventRepository $eventRepository,
        private EventScope $eventScope,
        private DateTagService $dateTagService,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'photos_2026_09_05_backfill_event_date_tags';
    }

    #[Override]
    public function execute(): void
    {
        foreach ($this->associationRepo->findEventIdsByType(PhotoService::ITEM_TYPE) as $eventId) {
            $event = $this->eventRepository->find($eventId);
            if (!$event instanceof Event) {
                continue;
            }

            $this->eventScope->runForEvent($eventId, fn(): null => $this->tagPhotosOf($event, $eventId));
        }
    }

    private function tagPhotosOf(Event $event, int $eventId): null
    {
        foreach ($this->associationRepo->findByEvent($eventId) as $association) {
            if ($association->getItemType() !== PhotoService::ITEM_TYPE) {
                continue;
            }

            $this->dateTagService->assign($event, (int) $association->getItemId());
        }

        return null;
    }
}
