<?php declare(strict_types=1);

namespace App\Review;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Form\BallotTermsType;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementListenerInterface;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FieldBallotService implements SettlementListenerInterface
{
    public const string PURPOSE = 'contribution.field';
    public const string STATE_DECIDING = 'deciding';
    public const string STATE_DECIDED = 'decided';
    public const string STATE_TIED = 'tied';

    public const TallyMode DEFAULT_MODE = TallyMode::Single;

    private const int KEEP = 0;

    public function __construct(
        private BallotInterface $ballots,
        private ChangeProposalService $proposals,
        private ChangeTargetRegistry $targets,
        private UserRepository $users,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $terms as collected by BallotTermsType; empty means its defaults
     */
    public function open(string $targetType, int $targetId, string $field, User $steward, array $terms = []): int
    {
        $provider = $this->targets->providerFor($targetType);
        if ($provider === null) {
            throw new ChangeProposalException('review.flash_ballot_impossible');
        }

        $candidates = $this->candidatesFor($provider, $targetType, $targetId, $field);
        if (count($candidates) < 2) {
            throw new ChangeProposalException('review.flash_ballot_impossible');
        }

        $settings = BallotTermsType::read($terms, self::DEFAULT_MODE);

        return $this->ballots->open(
            new BallotRequest(
                self::PURPOSE,
                $candidates,
                new DateTimeImmutable($settings['deadline']),
                (int) $steward->getId(),
                new BallotSubject($targetType, $targetId),
                $settings['tallyMode'],
                SettlementMode::Confirmed,
                $this->translator->trans('review.ballot_title', [
                    '%field%' => $provider->getFieldLabel($field),
                    '%target%' => $provider->getTargetLabel($targetId) ?? '',
                ]),
            ),
        );
    }

    public function confirm(int $ballotId, User $steward): void
    {
        $view = $this->ballots->view($ballotId, (int) $steward->getId());
        if ($view === null || $view->purpose !== self::PURPOSE) {
            throw new ChangeProposalException('review.flash_ballot_undecided');
        }

        if ($view->isOpen() && !$view->isDue(new DateTimeImmutable('now'))) {
            throw new ChangeProposalException('review.flash_ballot_running');
        }

        $winningKey = $view->winningKey ?? $this->ballots->tally($ballotId)->winningKey;
        if ($winningKey === null) {
            throw new ChangeProposalException('review.flash_ballot_undecided');
        }

        $this->ballots->settle($ballotId, $winningKey, (int) $steward->getId());
    }

    public function stateOf(BallotView $ballot): string
    {
        if ($ballot->isOpen() && !$ballot->isDue(new DateTimeImmutable('now'))) {
            return self::STATE_DECIDING;
        }

        return !$ballot->isOpen() && $ballot->winningKey === null ? self::STATE_TIED : self::STATE_DECIDED;
    }

    /**
     * @return array<string, BallotView>
     */
    public function unresolvedByField(string $targetType, int $targetId, int $viewerUserId): array
    {
        $byField = [];
        foreach ($this->ballots->listForSubject(new BallotSubject($targetType, $targetId), $viewerUserId) as $view) {
            $field = $view->status->isResolved() ? null : $this->fieldOf($view);
            if ($field !== null) {
                $byField[$field] = $view;
            }
        }

        return $byField;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function supports(string $purpose): bool
    {
        return $purpose === self::PURPOSE;
    }

    #[Override]
    public function settled(BallotOutcome $outcome): void
    {
        $subject = $outcome->subject;
        $winner = $outcome->winningKey === null ? null : $this->parse($outcome->winningKey);
        if ($subject === null || $winner === null) {
            return;
        }

        [$proposalId, $field] = $winner;
        $reviewer = $this->reviewerFor($outcome, $subject);
        if ($reviewer === null) {
            $this->logger->warning('FieldBallotService: nobody who may review this target is left, so the ballot wrote nothing', [
                'ballot_id' => $outcome->ballotId,
                'target_type' => $subject->type,
                'target_id' => $subject->id,
            ]);

            return;
        }

        $winningProposal = $proposalId === self::KEEP ? null : $this->proposals->get($proposalId);
        if ($proposalId !== self::KEEP && !$this->awaitsDecision($winningProposal, $field)) {
            $this->logger->warning('FieldBallotService: the winning proposal was resolved before the ballot was confirmed', [
                'ballot_id' => $outcome->ballotId,
                'proposal_id' => $proposalId,
                'field' => $field,
            ]);

            return;
        }

        if ($winningProposal !== null) {
            $this->proposals->applyField($winningProposal, $field, $reviewer);
        }

        $this->denyTheLosers($subject, $field, $reviewer, $winningProposal);
    }

    private function denyTheLosers(BallotSubject $subject, string $field, User $reviewer, ?ChangeProposal $winner): void
    {
        foreach ($this->proposals->pendingForTarget($subject->type, $subject->id) as $proposal) {
            if ($proposal->getId() === $winner?->getId() || !$this->awaitsDecision($proposal, $field)) {
                continue;
            }

            $this->proposals->denyField($proposal, $field, $reviewer);
        }
    }

    /**
     * @return list<Candidate>
     */
    private function candidatesFor(ChangeTargetProviderInterface $provider, string $targetType, int $targetId, string $field): array
    {
        $candidates = [];
        $seen = [];
        $currentValue = null;

        foreach ($this->proposals->pendingForTarget($targetType, $targetId) as $proposal) {
            foreach ($proposal->getUnresolvedChanges() as $change) {
                if ($change->field !== $field) {
                    continue;
                }

                $currentValue ??= $change->before;
                if (isset($seen[(string) $change->after])) {
                    continue;
                }

                $seen[(string) $change->after] = true;
                $candidates[] = new Candidate($this->keyFor((int) $proposal->getId(), $field), $provider->formatValue($field, $change->after));
            }
        }

        if ($candidates === []) {
            return [];
        }

        $candidates[] = new Candidate(
            $this->keyFor(self::KEEP, $field),
            $this->translator->trans('review.ballot_keep', [
                '%value%' => $provider->formatValue($field, $currentValue),
            ]),
        );

        return $candidates;
    }

    private function reviewerFor(BallotOutcome $outcome, BallotSubject $subject): ?User
    {
        foreach ([$outcome->settledByUserId, $outcome->openedByUserId] as $userId) {
            $candidate = $userId === null ? null : $this->users->find($userId);
            if ($candidate instanceof User && $this->proposals->canReviewTarget($subject->type, $subject->id, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function awaitsDecision(?ChangeProposal $proposal, string $field): bool
    {
        return $proposal !== null
        && $proposal->isPending()
        && array_any($proposal->getUnresolvedChanges(), static fn(FieldChange $change): bool => $change->field === $field);
    }

    private function fieldOf(BallotView $view): ?string
    {
        $first = $view->candidates[0] ?? null;
        $parsed = $first === null ? null : $this->parse($first->key);

        return $parsed === null ? null : $parsed[1];
    }

    private function keyFor(int $proposalId, string $field): string
    {
        return $proposalId . ':' . $field;
    }

    /**
     * @return array{int, string}|null
     */
    private function parse(string $key): ?array
    {
        $parts = explode(':', $key, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
            return null;
        }

        return [(int) $parts[0], $parts[1]];
    }
}
