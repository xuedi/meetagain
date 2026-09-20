<?php declare(strict_types=1);

namespace App\Portability\Ballot\Purpose;

use App\Entity\ChangeProposal;
use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Location;
use App\Enum\ChangeProposalStatus;
use App\Item\Tag\ChangeTarget;
use App\Portability\Ballot\PurposeInterface;
use App\Portability\DataCategory;
use App\Portability\ImportContext;
use App\Portability\Scope;
use App\Review\EventChangeTarget;
use App\Review\FieldBallotService;
use App\Review\LocationChangeTarget;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotSubject;
use Override;

readonly class FieldPurpose implements PurposeInterface
{
    private const int KEEP = 0;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === FieldBallotService::PURPOSE;
    }

    #[Override]
    public function inScope(string $purpose, ?BallotSubject $subject, array $candidateKeys, Scope $scope): bool
    {
        if ($subject === null || !$this->isTargetInScope($subject, $scope)) {
            return false;
        }

        $proposalIds = [];
        foreach ($candidateKeys as $key) {
            $proposalId = $this->proposalIdOf($key);
            if ($proposalId === null) {
                return false;
            }

            if ($proposalId !== self::KEEP) {
                $proposalIds[$proposalId] = $proposalId;
            }
        }

        if ($proposalIds === []) {
            return true;
        }

        $exportable = array_filter(
            $this->em->getRepository(ChangeProposal::class)->findBy(['id' => array_values($proposalIds)]),
            static fn(ChangeProposal $proposal): bool => $proposal->getStatus() === ChangeProposalStatus::Pending
            && $scope->grants($proposal->getProposedBy(), DataCategory::Interactions),
        );

        return count($exportable) === count($proposalIds);
    }

    #[Override]
    public function importSubject(BallotSubject $subject, ImportContext $context): ?BallotSubject
    {
        $type = $subject->type;
        $targetId = match (true) {
            $type === EventChangeTarget::TARGET_TYPE => $context->resolveRef(Event::class, $subject->id)?->getId(),
            $type === LocationChangeTarget::TARGET_TYPE => $context->resolveRef(Location::class, $subject->id)?->getId(),
            str_starts_with($type, ChangeTarget::TYPE_PREFIX) => $subject->id === ChangeTarget::VOCABULARY_TARGET
                ? ChangeTarget::VOCABULARY_TARGET
                : $context->resolveRef(ItemTag::class, $subject->id)?->getId(),
            default => $context->resolveItem($type, $subject->id),
        };

        return $targetId === null ? null : new BallotSubject($type, $targetId);
    }

    #[Override]
    public function importKey(string $purpose, string $key, ImportContext $context): ?string
    {
        $proposalId = $this->proposalIdOf($key);
        if ($proposalId === null) {
            return null;
        }

        if ($proposalId === self::KEEP) {
            return $key;
        }

        $importedId = $context->resolveRef(ChangeProposal::class, $proposalId)?->getId();

        return $importedId === null ? null : $importedId . substr($key, strlen((string) $proposalId));
    }

    private function isTargetInScope(BallotSubject $subject, Scope $scope): bool
    {
        $type = $subject->type;
        if ($type === EventChangeTarget::TARGET_TYPE) {
            return in_array($subject->id, $scope->eventIds, true);
        }

        if ($type === LocationChangeTarget::TARGET_TYPE) {
            return in_array($subject->id, $this->locationIds($scope), true);
        }

        if (str_starts_with($type, ChangeTarget::TYPE_PREFIX)) {
            $tagIds = $scope->tagIds[substr($type, strlen(ChangeTarget::TYPE_PREFIX))] ?? null;

            return $tagIds !== null && ($subject->id === ChangeTarget::VOCABULARY_TARGET || in_array($subject->id, $tagIds, true));
        }

        return in_array($subject->id, $scope->itemIds[$type] ?? [], true);
    }

    /**
     * @return list<int>
     */
    private function locationIds(Scope $scope): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $locationIds = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds]) as $event) {
            $locationId = $event->getLocation()?->getId();
            if ($locationId !== null) {
                $locationIds[$locationId] = $locationId;
            }
        }

        return array_values($locationIds);
    }

    private function proposalIdOf(string $key): ?int
    {
        $parts = explode(':', $key, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
            return null;
        }

        return (int) $parts[0];
    }
}
