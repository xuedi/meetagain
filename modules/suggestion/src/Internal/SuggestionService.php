<?php declare(strict_types=1);

namespace Module\Suggestion\Internal;

use App\Activity\ActivityService;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Suggestion\Contract\PortableSuggestion;
use Module\Suggestion\Contract\ScopeProviderInterface;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Contract\SuggestionInterface;
use Module\Suggestion\Contract\TargetProviderInterface;
use Module\Suggestion\Contract\View;
use Module\Suggestion\Internal\Activity\Approved;
use Module\Suggestion\Internal\Activity\Created;
use Module\Suggestion\Internal\Activity\Rejected;
use Module\Suggestion\Internal\Emails\Approved as ApprovedEmail;
use Module\Suggestion\Internal\Emails\Rejected as RejectedEmail;
use Module\Suggestion\Internal\Entity\Suggestion;
use Module\Suggestion\Internal\Repository\SuggestionRepository;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class SuggestionService implements SuggestionInterface
{
    /**
     * @param iterable<ScopeProviderInterface> $scopeProviders
     */
    public function __construct(
        private EntityManagerInterface $em,
        private SuggestionRepository $repo,
        private Registry $registry,
        private ActivityService $activityService,
        private ApprovedEmail $approvedEmail,
        private RejectedEmail $rejectedEmail,
        #[AutowireIterator(ScopeProviderInterface::class)]
        private iterable $scopeProviders = [],
    ) {}

    #[Override]
    public function providerFor(string $targetType): ?TargetProviderInterface
    {
        return $this->registry->providerFor($targetType);
    }

    #[Override]
    public function propose(string $targetType, int $proposerId, object $draft): ?string
    {
        $provider = $this->requireProvider($targetType);

        if (!$provider->canPropose($proposerId)) {
            throw new AccessDeniedException('Not allowed to suggest this target type.');
        }

        $error = $provider->validate($draft);
        if ($error !== null) {
            return $error;
        }

        $proposer = $this->em->getReference(User::class, $proposerId);
        $suggestion = new Suggestion();
        $suggestion->setTargetType($targetType);
        $suggestion->setProposedBy($proposer);
        $suggestion->setPayload($provider->toPayload($draft));
        $suggestion->setScope($this->scopeProvider()?->capture());

        $this->em->persist($suggestion);
        $this->em->flush();

        $this->activityService->log(Created::TYPE, $proposer, $this->activityMeta($suggestion));

        return null;
    }

    #[Override]
    public function pendingFor(int $proposerId, string $targetType): array
    {
        if (!$this->registry->has($targetType)) {
            return [];
        }

        return array_map($this->view(...), $this->repo->findPendingByProposer($proposerId, $targetType));
    }

    #[Override]
    public function find(int $id): ?View
    {
        $suggestion = $this->get($id);
        if ($suggestion === null || !$this->registry->has($suggestion->getTargetType())) {
            return null;
        }

        return $this->view($suggestion);
    }

    #[Override]
    public function restore(PortableSuggestion $suggestion): int
    {
        $row = new Suggestion();
        $row->setTargetType($suggestion->targetType);
        $row->setProposedBy($this->em->getReference(User::class, $suggestion->proposerId));
        $row->setPayload($suggestion->payload);
        $row->setScope($suggestion->scope);

        $this->em->persist($row);
        $this->em->flush();

        return (int) $row->getId();
    }

    public function approve(Suggestion $suggestion, object $editedDraft, User $reviewer): int
    {
        return $this->inScope($suggestion, fn(): int => $this->approveHere($suggestion, $editedDraft, $reviewer));
    }

    public function reject(Suggestion $suggestion, User $reviewer): void
    {
        $this->inScope($suggestion, function () use ($suggestion, $reviewer): void {
            $this->reviewableProvider($suggestion, $reviewer);
            $this->ensurePending($suggestion);

            $this->resolve($suggestion, $reviewer, Status::Rejected);

            $this->activityService->log(Rejected::TYPE, $reviewer, $this->activityMeta($suggestion));
            $this->rejectedEmail->send($this->mailContext($suggestion));
        });
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function inScope(Suggestion $suggestion, callable $work): mixed
    {
        $scope = $suggestion->getScope();
        $provider = $this->scopeProvider();
        if ($scope === null || $provider === null) {
            return $work();
        }

        return $provider->runIn($scope, $work);
    }

    private function approveHere(Suggestion $suggestion, object $editedDraft, User $reviewer): int
    {
        $provider = $this->reviewableProvider($suggestion, $reviewer);
        $this->ensurePending($suggestion);

        $error = $provider->validate($editedDraft);
        if ($error !== null) {
            throw new SuggestionException($error);
        }

        $createdId = $provider->create($editedDraft, (int) $suggestion->getProposedBy()->getId());

        $suggestion->setPayload($provider->toPayload($editedDraft));
        $suggestion->setCreatedId($createdId);
        $this->resolve($suggestion, $reviewer, Status::Approved);

        $this->activityService->log(Approved::TYPE, $reviewer, $this->activityMeta($suggestion));
        $this->approvedEmail->send($this->mailContext($suggestion));

        return $createdId;
    }

    public function withdraw(Suggestion $suggestion, User $user): void
    {
        if ($suggestion->getProposedBy()->getId() !== $user->getId()) {
            throw new AccessDeniedException('Only the proposer can withdraw a suggestion.');
        }
        $this->ensurePending($suggestion);

        $this->resolve($suggestion, null, Status::Withdrawn);
    }

    public function get(int $id): ?Suggestion
    {
        return $this->repo->find($id);
    }

    /** @return list<Suggestion> */
    public function pendingReviewableBy(User $user): array
    {
        $reviewable = [];
        foreach ($this->repo->findPending() as $suggestion) {
            if ($this->canReview($suggestion, $user)) {
                $reviewable[] = $suggestion;
            }
        }

        return $reviewable;
    }

    public function hasProvider(string $targetType): bool
    {
        return $this->registry->has($targetType);
    }

    public function canReview(Suggestion $suggestion, User $user): bool
    {
        $provider = $this->registry->providerFor($suggestion->getTargetType());
        if ($provider === null) {
            return false;
        }

        return $this->inScope($suggestion, static fn(): bool => $provider->canReview((int) $user->getId()));
    }

    public function draftFor(Suggestion $suggestion): object
    {
        return $this->requireProvider($suggestion->getTargetType())->fromPayload($suggestion->getPayload());
    }

    public function describe(Suggestion $suggestion): string
    {
        return $this->registry->providerFor($suggestion->getTargetType())?->describe($suggestion->getPayload()) ?? '';
    }

    /** @return list<array{label: string, value: string}> */
    public function summaryRows(Suggestion $suggestion): array
    {
        return $this->registry->providerFor($suggestion->getTargetType())?->summaryRows($suggestion->getPayload()) ?? [];
    }

    public function requireProvider(string $targetType): TargetProviderInterface
    {
        $provider = $this->registry->providerFor($targetType);
        if ($provider === null) {
            throw new InvalidArgumentException(sprintf('No active suggestion target registered for type "%s"', $targetType));
        }

        return $provider;
    }

    private function view(Suggestion $suggestion): View
    {
        return new View(
            id: (int) $suggestion->getId(),
            targetType: $suggestion->getTargetType(),
            status: $suggestion->getStatus(),
            description: $this->describe($suggestion),
            rows: $this->summaryRows($suggestion),
            createdId: $suggestion->getCreatedId(),
        );
    }

    private function reviewableProvider(Suggestion $suggestion, User $reviewer): TargetProviderInterface
    {
        $provider = $this->requireProvider($suggestion->getTargetType());
        if (!$provider->canReview((int) $reviewer->getId())) {
            throw new AccessDeniedException('Not allowed to review suggestions of this target type.');
        }

        return $provider;
    }

    private function scopeProvider(): ?ScopeProviderInterface
    {
        foreach ($this->scopeProviders as $provider) {
            return $provider;
        }

        return null;
    }

    private function ensurePending(Suggestion $suggestion): void
    {
        if (!$suggestion->isPending()) {
            throw new SuggestionException('review_suggestion.flash_not_pending');
        }
    }

    private function resolve(Suggestion $suggestion, ?User $reviewer, Status $status): void
    {
        $suggestion->setStatus($status);
        $suggestion->setReviewedBy($reviewer);
        $suggestion->setResolvedAt(new DateTimeImmutable());
        $this->em->flush();
    }

    /** @return array{user: User, description: string} */
    private function mailContext(Suggestion $suggestion): array
    {
        return ['user' => $suggestion->getProposedBy(), 'description' => $this->describe($suggestion)];
    }

    /** @return array{target_type: string, created_id: ?int, description: string} */
    private function activityMeta(Suggestion $suggestion): array
    {
        return [
            'target_type' => $suggestion->getTargetType(),
            'created_id' => $suggestion->getCreatedId(),
            'description' => $this->describe($suggestion),
        ];
    }
}
