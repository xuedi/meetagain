<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\EventItemAssociation;
use App\Entity\User;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class EventItemsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'event_items';
    }

    #[Override]
    public function getOrder(): int
    {
        return 65;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $links = array_values(array_filter(
            $this->em->getRepository(EventItemAssociation::class)->findBy(['event' => $scope->eventIds], ['id' => 'ASC']),
            static fn(EventItemAssociation $link): bool => in_array($link->getItemId(), $scope->itemIds[(string) $link->getItemType()] ?? [], true),
        ));
        $creators = $this->creators($links);

        $rows = [];
        foreach ($links as $link) {
            $rows[] = [
                'event_ref' => $link->getEvent()?->getId(),
                'item_type' => $link->getItemType(),
                'item_ref' => $link->getItemId(),
                'position' => $link->getPosition(),
                'section_label' => $link->getSectionLabel(),
                'created_by_email' => $scope->creditEmail($creators[(int) $link->getCreatedBy()] ?? null),
                'created_at' => $link->getCreatedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemType = (string) ($row['item_type'] ?? '');
            if (!$context->knowsItemType($itemType)) {
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $event = $context->resolveRef(Event::class, $row['event_ref'] ?? null);
            $itemId = $context->resolveItem($itemType, $row['item_ref'] ?? null);
            if (!$event instanceof Event || $itemId === null) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $creator = $context->resolveRef(User::class, $row['created_by_email'] ?? null) ?? $context->getSystemUser();
            $createdAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) ($row['created_at'] ?? ''));
            $position = $row['position'] ?? null;
            $sectionLabel = $row['section_label'] ?? null;

            $link = new EventItemAssociation()
                ->setEvent($event)
                ->setItemType($itemType)
                ->setItemId($itemId)
                ->setCreatedBy((int) $creator->getId())
                ->setCreatedAt($createdAt === false ? new DateTimeImmutable() : $createdAt)
                ->setPosition($position === null ? null : (int) $position)
                ->setSectionLabel($sectionLabel === null ? null : (string) $sectionLabel);

            $this->em->persist($link);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param list<EventItemAssociation> $links
     * @return array<int, User>
     */
    private function creators(array $links): array
    {
        $creatorIds = array_values(array_unique(array_map(static fn(EventItemAssociation $link): int => (int) $link->getCreatedBy(), $links)));
        if ($creatorIds === []) {
            return [];
        }

        $creators = [];
        foreach ($this->userRepository->findBy(['id' => $creatorIds]) as $user) {
            $creators[(int) $user->getId()] = $user;
        }

        return $creators;
    }
}
