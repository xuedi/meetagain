<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Change;

use App\Activity\ActivityService;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\FieldResolution;
use Module\Proposal\Contract\Change\PortableChange;
use Module\Proposal\Contract\Change\ScopedTargetInterface;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Module\Proposal\Contract\Change\View;
use Module\Proposal\Contract\Status;
use Module\Proposal\Internal\Activity\Change\Applied;
use Module\Proposal\Internal\Activity\Change\Created;
use Module\Proposal\Internal\Activity\Change\Rejected;
use Module\Proposal\Internal\ChangeException;
use Module\Proposal\Internal\Entity\Change;
use Module\Proposal\Internal\Repository\ChangeRepository;
use Override;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class ChangeService implements ChangeInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ChangeRepository $repo,
        private Registry $registry,
        private ActivityService $activityService,
    ) {}

    #[Override]
    public function propose(string $targetType, int $targetId, int $proposerId, array $changes): ?int
    {
        $provider = $this->providerFor($targetType);

        $changed = array_values(array_filter($changes, static fn(FieldChange $change): bool => $change->before !== $change->after));
        if ($changed === []) {
            return null;
        }

        $proposer = $this->em->find(User::class, $proposerId);
        if (!$proposer instanceof User || !$provider->canPropose($proposerId, $targetId)) {
            throw new AccessDeniedException('Not allowed to propose changes to this target.');
        }

        $proposal = new Change();
        $proposal->setTargetType($targetType);
        $proposal->setTargetId($targetId);
        $proposal->setProposedBy($proposer);
        $proposal->setChanges($changed);

        $this->em->persist($proposal);
        $this->em->flush();

        $this->activityService->log(Created::TYPE, $proposer, $this->activityMeta($proposal));

        return (int) $proposal->getId();
    }

    public function applyField(Change $proposal, string $field, User $reviewer, ?string $scope = null): void
    {
        $provider = $this->reviewableProvider($proposal, $reviewer);
        $change = $this->unresolvedChange($proposal, $field);
        $scoped = $scope === null ? null : $this->scopedProvider($provider, $proposal->getTargetId(), $scope);

        $error = $provider->validate($proposal->getTargetId(), $field, $change->after);
        if ($error !== null) {
            throw new ChangeException($error);
        }

        $provider->apply($proposal->getTargetId(), $field, $change->after);
        $scoped?->applyToScope($proposal->getTargetId(), (string) $scope, $field, $change->after);
        $proposal->resolveField($field, FieldResolution::Applied);

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function applyAppliedFieldToScope(Change $proposal, string $field, User $reviewer, string $scope): void
    {
        $provider = $this->reviewableProvider($proposal, $reviewer);
        $change = $proposal->getChange($field);
        if ($change->resolution !== FieldResolution::Applied) {
            throw new ChangeException('review.flash_scope_unavailable');
        }

        $this->scopedProvider($provider, $proposal->getTargetId(), $scope)->applyToScope($proposal->getTargetId(), $scope, $field, $change->after);
    }

    /** @return array{key: string, label: string}|null */
    public function applyScopeFor(string $targetType, int $targetId): ?array
    {
        $provider = $this->registry->providerFor($targetType);

        return $provider instanceof ScopedTargetInterface ? $provider->getApplyScope($targetId) : null;
    }

    public function assertApplyScope(string $targetType, int $targetId, string $scope): void
    {
        $this->scopedProvider($this->providerFor($targetType), $targetId, $scope);
    }

    public function denyField(Change $proposal, string $field, User $reviewer): void
    {
        $this->reviewableProvider($proposal, $reviewer);
        $this->unresolvedChange($proposal, $field);

        $proposal->resolveField($field, FieldResolution::Denied);

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function approveAll(Change $proposal, User $reviewer): void
    {
        $provider = $this->reviewableProvider($proposal, $reviewer);
        $this->ensurePending($proposal);

        $unresolved = $proposal->getUnresolvedChanges();
        foreach ($unresolved as $change) {
            $error = $provider->validate($proposal->getTargetId(), $change->field, $change->after);
            if ($error !== null) {
                throw new ChangeException($error);
            }
        }

        foreach ($unresolved as $change) {
            $provider->apply($proposal->getTargetId(), $change->field, $change->after);
            $proposal->resolveField($change->field, FieldResolution::Applied);
        }

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function rejectAll(Change $proposal, User $reviewer): void
    {
        $this->reviewableProvider($proposal, $reviewer);
        $this->ensurePending($proposal);

        foreach ($proposal->getUnresolvedChanges() as $change) {
            $proposal->resolveField($change->field, FieldResolution::Denied);
        }

        $this->finalizeIfResolved($proposal, $reviewer);
    }

    public function withdraw(Change $proposal, User $user): void
    {
        if ($proposal->getProposedBy()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Only the proposer can withdraw a proposal.');
        }
        $this->ensurePending($proposal);

        $proposal->setStatus(Status::Withdrawn);
        $this->em->flush();
    }

    #[Override]
    public function removeForTarget(string $targetType, int $targetId): void
    {
        $this->repo->removeForTarget($targetType, $targetId);
    }

    public function get(int $id): ?Change
    {
        return $this->repo->find($id);
    }

    #[Override]
    public function find(int $id): ?View
    {
        $proposal = $this->repo->find($id);

        return $proposal === null ? null : $this->view($proposal);
    }

    /** @return list<Change> */
    public function pendingForTarget(string $targetType, int $targetId): array
    {
        return $this->repo->findPendingForTarget($targetType, $targetId);
    }

    #[Override]
    public function pendingFor(string $targetType, int $targetId): array
    {
        return array_map($this->view(...), $this->repo->findPendingForTarget($targetType, $targetId));
    }

    #[Override]
    public function pendingTargetIds(string $targetType): array
    {
        return $this->repo->pendingTargetIds($targetType);
    }

    #[Override]
    public function countPending(string $targetType, ?int $targetId = null): int
    {
        return $targetId === null ? $this->repo->countPendingForTargetType($targetType) : $this->repo->countPendingForTarget($targetType, $targetId);
    }

    #[Override]
    public function exportPending(array $targetTypes): array
    {
        if ($targetTypes === []) {
            return [];
        }

        return array_map($this->view(...), $this->repo->findBy(['status' => Status::Pending, 'targetType' => $targetTypes], ['id' => 'ASC']));
    }

    #[Override]
    public function restore(PortableChange $change): int
    {
        $proposal = new Change();
        $proposal->setTargetType($change->targetType);
        $proposal->setTargetId($change->targetId);
        $proposal->setProposedBy($this->em->getReference(User::class, $change->proposerId));
        $proposal->setChanges($change->changes);
        $proposal->setCreatedAt($change->createdAt);

        $this->em->persist($proposal);
        $this->em->flush();

        return (int) $proposal->getId();
    }

    /** @return list<Change> */
    public function pendingReviewableBy(User $user): array
    {
        $reviewable = [];
        foreach ($this->repo->findPending() as $proposal) {
            $provider = $this->registry->providerFor($proposal->getTargetType());
            if ($provider === null || $provider->getTargetLabel($proposal->getTargetId()) === null) {
                continue;
            }
            if (!$provider->canReview((int) $user->getId(), $proposal->getTargetId())) {
                continue;
            }

            $reviewable[] = $proposal;
        }

        return $reviewable;
    }

    public function canProposeTarget(string $targetType, int $targetId, User $user): bool
    {
        $provider = $this->registry->providerFor($targetType);

        return $provider !== null && $provider->canPropose((int) $user->getId(), $targetId);
    }

    public function canReviewTarget(string $targetType, int $targetId, User $user): bool
    {
        $provider = $this->registry->providerFor($targetType);

        return $provider !== null && $provider->canReview((int) $user->getId(), $targetId);
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
     * @return list<array{field: string, label: string, before: string, after: string, resolution: ?FieldResolution}>
     */
    public function fieldRows(Change $proposal): array
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

    private function view(Change $proposal): View
    {
        return new View(
            id: (int) $proposal->getId(),
            targetType: $proposal->getTargetType(),
            targetId: $proposal->getTargetId(),
            proposerId: (int) $proposal->getProposedBy()->getId(),
            proposerName: $proposal->getProposedBy()->getName() ?? '',
            status: $proposal->getStatus(),
            createdAt: $proposal->getCreatedAt(),
            changes: $proposal->getChanges(),
            rows: $this->fieldRows($proposal),
        );
    }

    private function providerFor(string $targetType): TargetProviderInterface
    {
        $provider = $this->registry->providerFor($targetType);
        if ($provider === null) {
            throw new InvalidArgumentException(sprintf('No active change target registered for type "%s"', $targetType));
        }

        return $provider;
    }

    private function scopedProvider(TargetProviderInterface $provider, int $targetId, string $scope): ScopedTargetInterface
    {
        if (!$provider instanceof ScopedTargetInterface || ($provider->getApplyScope($targetId)['key'] ?? null) !== $scope) {
            throw new ChangeException('review.flash_scope_unavailable');
        }

        return $provider;
    }

    private function reviewableProvider(Change $proposal, User $reviewer): TargetProviderInterface
    {
        $provider = $this->providerFor($proposal->getTargetType());
        if (!$provider->canReview((int) $reviewer->getId(), $proposal->getTargetId())) {
            throw new AccessDeniedException('Not allowed to review changes to this target.');
        }

        return $provider;
    }

    private function ensurePending(Change $proposal): void
    {
        if (!$proposal->isPending()) {
            throw new ChangeException('review.flash_not_pending');
        }
    }

    private function unresolvedChange(Change $proposal, string $field): FieldChange
    {
        $this->ensurePending($proposal);

        $change = $proposal->getChange($field);
        if ($change->isResolved()) {
            throw new ChangeException('review.flash_already_resolved');
        }

        return $change;
    }

    private function finalizeIfResolved(Change $proposal, User $reviewer): void
    {
        if (!$proposal->isFullyResolved()) {
            $this->em->flush();

            return;
        }

        $approved = $proposal->hasAppliedField();
        $proposal->setStatus($approved ? Status::Approved : Status::Rejected);
        $proposal->setReviewedBy($reviewer);
        $proposal->setReviewedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->activityService->log($approved ? Applied::TYPE : Rejected::TYPE, $reviewer, $this->activityMeta($proposal));
    }

    /** @return array{target_type: string, target_id: int, target_label: string} */
    private function activityMeta(Change $proposal): array
    {
        return [
            'target_type' => $proposal->getTargetType(),
            'target_id' => $proposal->getTargetId(),
            'target_label' => $this->targetLabel($proposal->getTargetType(), $proposal->getTargetId()) ?? '',
        ];
    }
}
