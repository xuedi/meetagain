<?php declare(strict_types=1);

namespace Plugin\Photos\Event;

use App\Entity\Event;
use App\Entity\ItemTag;
use App\Item\Tag\ManagedWriter;
use App\Item\Tag\TagService;
use App\Repository\EventRepository;
use App\Service\Item\AssociationService;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\PhotoService;

readonly class AssociationWriter
{
    public function __construct(
        private AssociationService $associations,
        private EventRepository $eventRepository,
        private DateTagService $dateTagService,
        private ManagedWriter $managedWriter,
        private TagService $tagService,
    ) {}

    public function setEvent(Photo $photo, ?Event $event): void
    {
        $photoId = (int) $photo->getId();
        $wanted = $event === null ? [] : [(int) $event->getId()];
        $current = $this->associations->eventIdsForItem(PhotoService::ITEM_TYPE, $photoId);
        if ($current === $wanted) {
            return;
        }

        foreach ($current as $eventId) {
            $this->clear($photoId, $eventId);
        }

        if ($event === null) {
            return;
        }

        $this->associations->attach((int) $event->getId(), PhotoService::ITEM_TYPE, $photoId, (int) $photo->getCreatedBy());
        $this->dateTagService->assign($event, $photoId);
    }

    private function clear(int $photoId, int $eventId): void
    {
        $this->associations->detach($eventId, PhotoService::ITEM_TYPE, $photoId);

        $event = $this->eventRepository->find($eventId);
        $tag = $event instanceof Event ? $this->dateTagService->findDateTag($event) : null;
        if ($tag === null) {
            return;
        }

        $this->managedWriter->unassign($tag, $photoId);
        $this->dropIfUnused($tag);
    }

    private function dropIfUnused(ItemTag $tag): void
    {
        $usage = $this->tagService->getUsage(PhotoService::ITEM_TYPE);
        if (($usage[(int) $tag->getId()] ?? 0) > 0) {
            return;
        }

        $this->tagService->deleteTag($tag);
    }
}
