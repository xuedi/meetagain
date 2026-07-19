<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\EventItemAssociation;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Repository\EventItemAssociationRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Attaches, detaches and lists the universal event-to-item associations, dispatching the
 * neutral association EntityAction cases after flush so subsystems can react.
 */
readonly class ItemAssociationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventItemAssociationRepository $repository,
        private EventRepository $eventRepository,
        private EntityActionDispatcher $entityActionDispatcher,
    ) {}

    public function attach(int $eventId, string $itemType, int $itemId, int $createdBy, ?int $position = null, ?string $sectionLabel = null): EventItemAssociation
    {
        $existing = $this->repository->findOneByEventAndItem($eventId, $itemType, $itemId);
        if ($existing !== null) {
            return $existing;
        }

        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw new RuntimeException(sprintf('Cannot attach item to unknown event %d.', $eventId));
        }

        $association = new EventItemAssociation();
        $association->setEvent($event);
        $association->setItemType($itemType);
        $association->setItemId($itemId);
        $association->setCreatedBy($createdBy);
        $association->setCreatedAt(new DateTimeImmutable());
        $association->setPosition($position);
        $association->setSectionLabel($sectionLabel);

        $this->em->persist($association);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::CreateEventItemAssociation, (int) $association->getId());

        return $association;
    }

    public function detach(int $eventId, string $itemType, int $itemId): void
    {
        $association = $this->repository->findOneByEventAndItem($eventId, $itemType, $itemId);
        if ($association === null) {
            return;
        }

        $associationId = (int) $association->getId();
        $this->em->remove($association);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteEventItemAssociation, $associationId);
    }

    /**
     * @return EventItemAssociation[]
     */
    public function listForEvent(int $eventId): array
    {
        return $this->repository->findByEvent($eventId);
    }
}
