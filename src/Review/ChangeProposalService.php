<?php declare(strict_types=1);

namespace App\Review;

use App\Activity\ActivityService;
use App\Activity\Messages\ChangeProposalApplied;
use App\Activity\Messages\ChangeProposalCreated;
use App\Activity\Messages\ChangeProposalRejected;
use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Repository\ChangeProposalRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class ChangeProposalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ChangeProposalRepository $repo,
        private ChangeTargetRegistry $registry,
        private ActivityService $activityService,
    ) {}

    /**
     * @param list<FieldChange> $changes
     */
    public function propose(string $targetType, int $targetId, User $proposer, array $changes): ?ChangeProposal
    {
        $provider = $this->providerFor($targetType);

        $changed = array_values(array_filter($changes, static fn(FieldChange $change): bool => $change->before !== $change->after));
        if ($changed === []) {
            return null;
        }

        if (!$provider->canPropose($proposer, $targetId)) {
            throw new AccessDeniedException('Not allowed to propose changes to this target.');
        }

        $proposal = new ChangeProposal();
        $proposal->setTargetType($targetType);
        $proposal->setTargetId($targetId);
        $proposal->setProposedBy($proposer);
        $proposal->setChanges($changed);

        $this->em->persist($proposal);
        $this->em->flush();

        $this->activityService->log(ChangeProposalCreated::TYPE, $proposer, $this->activityMeta($proposal));

        return $proposal;
    }

    public function applyField(ChangeProposal $proposal, string $field, User $reviewer): void
    {
        $provider = $this->reviewableProvider($proposal, $reviewer);
        $change = $this->unresolvedChange($proposal, $field);

        $error = $provider->validate($proposal->getTargetId(), $field, $change->after);
        if ($error !== null) {
            throw new ChangeProposalException($error);
        }

        $provider->apply($proposal->getTargetId(), $field, $change->after);
        $proposal->resolveField($field, FieldResolution::Applied);

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function denyField(ChangeProposal $proposal, string $field, User $reviewer): void
    {
        $this->reviewableProvider($proposal, $reviewer);
        $this->unresolvedChange($proposal, $field);

        $proposal->resolveField($field, FieldResolution::Denied);

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function approveAll(ChangeProposal $proposal, User $reviewer): void
    {
        $provider = $this->reviewableProvider($proposal, $reviewer);
        $this->ensurePending($proposal);

        $unresolved = $proposal->getUnresolvedChanges();
        foreach ($unresolved as $change) {
            $error = $provider->validate($proposal->getTargetId(), $change->field, $change->after);
            if ($error !== null) {
                throw new ChangeProposalException($error);
            }
        }

        foreach ($unresolved as $change) {
            $provider->apply($proposal->getTargetId(), $change->field, $change->after);
            $proposal->resolveField($change->field, FieldResolution::Applied);
        }

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function rejectAll(ChangeProposal $proposal, User $reviewer): void
    {
        $this->reviewableProvider($proposal, $reviewer);
        $this->ensurePending($proposal);

        foreach ($proposal->getUnresolvedChanges() as $change) {
            $proposal->resolveField($change->field, FieldResolution::Denied);
        }

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function withdraw(ChangeProposal $proposal, User $user): void
    {
        if ($proposal->getProposedBy()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Only the proposer can withdraw a proposal.');
        }
        $this->ensurePending($proposal);

        $proposal->setStatus(ChangeProposalStatus::Withdrawn);
        $this->em->flush();
    }

    public function removeForTarget(string $targetType, int $targetId): void
    {
        $this->repo->removeForTarget($targetType, $targetId);
    }

    public function get(int $id): ?ChangeProposal
    {
        return $this->repo->find($id);
    }

    /** @return ChangeProposal[] */
    public function pendingForTarget(string $targetType, int $targetId): array
    {
        return $this->repo->findPendingForTarget($targetType, $targetId);
    }

    /** @return list<int> */
    public function pendingTargetIds(string $targetType): array
    {
        return $this->repo->pendingTargetIds($targetType);
    }

    public function countPendingForTarget(string $targetType, int $targetId): int
    {
        return $this->repo->countPendingForTarget($targetType, $targetId);
    }

    /** @return list<ChangeProposal> */
    public function pendingReviewableBy(User $user): array
    {
        $reviewable = [];
        foreach ($this->repo->findPending() as $proposal) {
            $provider = $this->registry->providerFor($proposal->getTargetType());
            if ($provider === null || $provider->getTargetLabel($proposal->getTargetId()) === null) {
                continue;
            }
            if (!$provider->canReview($user, $proposal->getTargetId())) {
                continue;
            }

            $reviewable[] = $proposal;
        }

        return $reviewable;
    }

    public function canReviewTarget(string $targetType, int $targetId, User $user): bool
    {
        $provider = $this->registry->providerFor($targetType);

        return $provider !== null && $provider->canReview($user, $targetId);
    }

    public function targetLabel(string $targetType, int $targetId): ?string
    {
        return $this->registry->providerFor($targetType)?->getTargetLabel($targetId);
    }

    public function targetUrl(string $targetType, int $targetId): ?string
    {
        return $this->registry->providerFor($targetType)?->getTargetUrl($targetId);
    }

    /**
     * Display rows for the proposal's field diff, formatted through the target provider.
     *
     * @return list<array{field: string, label: string, before: string, after: string, resolution: ?FieldResolution}>
     */
    public function fieldRows(ChangeProposal $proposal): array
    {
        $provider = $this->registry->providerFor($proposal->getTargetType());
        if ($provider === null) {
            return [];
        }

        $rows = [];
        foreach ($proposal->getChanges() as $change) {
            $rows[] = [
                'field' => $change->field,
                'label' => $provider->getFieldLabel($change->field),
                'before' => $provider->formatValue($change->field, $change->before),
                'after' => $provider->formatValue($change->field, $change->after),
                'resolution' => $change->resolution,
            ];
        }

        return $rows;
    }

    private function providerFor(string $targetType): ChangeTargetProviderInterface
    {
        $provider = $this->registry->providerFor($targetType);
        if ($provider === null) {
            throw new InvalidArgumentException(sprintf('No active change target registered for type "%s"', $targetType));
        }

        return $provider;
    }

    private function reviewableProvider(ChangeProposal $proposal, User $reviewer): ChangeTargetProviderInterface
    {
        $provider = $this->providerFor($proposal->getTargetType());
        if (!$provider->canReview($reviewer, $proposal->getTargetId())) {
            throw new AccessDeniedException('Not allowed to review changes to this target.');
        }

        return $provider;
    }

    private function ensurePending(ChangeProposal $proposal): void
    {
        if (!$proposal->isPending()) {
            throw new ChangeProposalException('review.flash_not_pending');
        }
    }

    private function unresolvedChange(ChangeProposal $proposal, string $field): FieldChange
    {
        $this->ensurePending($proposal);

        $change = $proposal->getChange($field);
        if ($change->isResolved()) {
            throw new ChangeProposalException('review.flash_already_resolved');
        }

        return $change;
    }

    private function finalizeIfResolved(ChangeProposal $proposal, User $reviewer): void
    {
        if (!$proposal->isFullyResolved()) {
            $this->em->flush();

            return;
        }

        $approved = $proposal->hasAppliedField();
        $proposal->setStatus($approved ? ChangeProposalStatus::Approved : ChangeProposalStatus::Rejected);
        $proposal->setReviewedBy($reviewer);
        $proposal->setReviewedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->activityService->log(
            $approved ? ChangeProposalApplied::TYPE : ChangeProposalRejected::TYPE,
            $reviewer,
            $this->activityMeta($proposal),
        );
    }

    /** @return array{target_type: string, target_id: int, target_label: string} */
    private function activityMeta(ChangeProposal $proposal): array
    {
        return [
            'target_type' => $proposal->getTargetType(),
            'target_id' => $proposal->getTargetId(),
            'target_label' => $this->targetLabel($proposal->getTargetType(), $proposal->getTargetId()) ?? '',
        ];
    }
}
