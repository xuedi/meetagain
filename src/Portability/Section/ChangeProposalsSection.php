<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\ChangeProposal;
use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Item\Tag\ChangeTarget;
use App\Item\Tag\TypeRegistry;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Review\EventChangeTarget;
use App\Review\FieldChange;
use App\Review\LocationChangeTarget;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class ChangeProposalsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TypeRegistry $tagTypes,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'change_proposals';
    }

    #[Override]
    public function getOrder(): int
    {
        return 86;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $targets = $this->exportableTargets($scope);
        if ($targets === []) {
            return [];
        }

        $proposals = $this->em->getRepository(ChangeProposal::class)->findBy([
            'status' => ChangeProposalStatus::Pending,
            'targetType' => array_keys($targets),
        ], ['id' => 'ASC']);

        $rows = [];
        foreach ($proposals as $proposal) {
            $isTargetExported = in_array($proposal->getTargetId(), $targets[$proposal->getTargetType()] ?? [], true);
            if (!$isTargetExported || !$scope->grants($proposal->getProposedBy(), DataCategory::Interactions)) {
                continue;
            }

            $changes = [];
            foreach ($proposal->getChanges() as $change) {
                $changes[$change->field] = $change->toArray();
            }

            $rows[] = [
                'ref' => (int) $proposal->getId(),
                'target_type' => $proposal->getTargetType(),
                'target_ref' => $proposal->getTargetId(),
                'changes' => $changes,
                'email' => $proposal->getProposedBy()->getEmail(),
                'created_at' => $proposal->getCreatedAt()->format(DateTimeInterface::ATOM),
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

            $targetType = (string) ($row['target_type'] ?? '');
            $tagType = str_starts_with($targetType, ChangeTarget::TYPE_PREFIX) ? substr($targetType, strlen(ChangeTarget::TYPE_PREFIX)) : null;
            $isCoreTarget = in_array($targetType, [EventChangeTarget::TARGET_TYPE, LocationChangeTarget::TARGET_TYPE], true);
            $isTaggableHere = $tagType !== null && $this->tagTypes->has($tagType);
            if (!$isCoreTarget && !$isTaggableHere) {
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $targetId = $this->resolveTarget($targetType, $tagType !== null, $row['target_ref'] ?? null, $context);
            $proposer = $context->resolveRef(User::class, $row['email'] ?? null);
            $changes = $this->readChanges($row['changes'] ?? null, $tagType !== null, $context);
            if ($targetId === null || !$proposer instanceof User || $changes === null) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $proposal = new ChangeProposal();
            $proposal->setTargetType($targetType);
            $proposal->setTargetId($targetId);
            $proposal->setProposedBy($proposer);
            $proposal->setStatus(ChangeProposalStatus::Pending);
            $proposal->setChanges($changes);
            $proposal->setCreatedAt($this->readDate($row['created_at'] ?? null) ?? new DateTimeImmutable());

            $this->em->persist($proposal);
            if (isset($row['ref'])) {
                $context->mapRef(ChangeProposal::class, (int) $row['ref'], $proposal);
            }

            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @return array<string, list<int>>
     */
    private function exportableTargets(Scope $scope): array
    {
        $targets = [];
        if ($scope->eventIds !== []) {
            $targets[EventChangeTarget::TARGET_TYPE] = $scope->eventIds;
            $targets[LocationChangeTarget::TARGET_TYPE] = $this->locationIds($scope->eventIds);
        }

        foreach ($scope->tagIds as $itemType => $tagIds) {
            $targets[ChangeTarget::TYPE_PREFIX . $itemType] = [ChangeTarget::VOCABULARY_TARGET, ...$tagIds];
        }

        return $targets;
    }

    /**
     * @param list<int> $eventIds
     * @return list<int>
     */
    private function locationIds(array $eventIds): array
    {
        $locationIds = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $eventIds]) as $event) {
            $locationId = $event->getLocation()?->getId();
            if ($locationId !== null) {
                $locationIds[$locationId] = $locationId;
            }
        }

        return array_values($locationIds);
    }

    private function resolveTarget(string $targetType, bool $isTagFamily, mixed $ref, ImportContext $context): ?int
    {
        if ($isTagFamily && $ref === ChangeTarget::VOCABULARY_TARGET) {
            return ChangeTarget::VOCABULARY_TARGET;
        }

        return match ($targetType) {
            EventChangeTarget::TARGET_TYPE => $context->resolveRef(Event::class, $ref)?->getId(),
            LocationChangeTarget::TARGET_TYPE => $context->resolveRef(Location::class, $ref)?->getId(),
            default => $context->resolveRef(ItemTag::class, $ref)?->getId(),
        };
    }

    /**
     * @return list<FieldChange>|null
     */
    private function readChanges(mixed $changes, bool $isTagFamily, ImportContext $context): ?array
    {
        if (!is_array($changes) || $changes === []) {
            return null;
        }

        $fieldChanges = [];
        foreach ($changes as $field => $change) {
            if (!is_array($change)) {
                continue;
            }

            $before = $this->readValue($change['before'] ?? null);
            $after = $this->readValue($change['after'] ?? null);
            if ($isTagFamily && $field === ChangeTarget::FIELD_PARENT) {
                $rekeyedBefore = $this->rekeyParent($before, $context);
                $rekeyedAfter = $this->rekeyParent($after, $context);
                $isBeforeLost = !$this->isBlank($before) && $rekeyedBefore === null;
                $isAfterLost = !$this->isBlank($after) && $rekeyedAfter === null;
                if ($isBeforeLost || $isAfterLost) {
                    return null;
                }

                $before = $rekeyedBefore;
                $after = $rekeyedAfter;
            }

            $resolution = $change['resolution'] ?? null;
            $fieldChanges[] = new FieldChange((string) $field, $before, $after, is_string($resolution) ? FieldResolution::tryFrom($resolution) : null);
        }

        return $fieldChanges === [] ? null : $fieldChanges;
    }

    private function rekeyParent(?string $value, ImportContext $context): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $tagId = $context->resolveRef(ItemTag::class, (int) $value)?->getId();

        return $tagId === null ? null : (string) $tagId;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || $value === '';
    }

    private function readValue(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function readDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }
}
