<?php declare(strict_types=1);

namespace Module\Ballot\Contract;

/**
 * The whole outbound surface of the Ballot module. Candidates are opaque keys the caller chose and
 * the module never resolves; every answer is a read model, never an entity.
 */
interface BallotInterface
{
    /** @return int the new ballot's id */
    public function open(BallotRequest $request): int;

    /**
     * Records this member's approvals, replacing whatever they chose before.
     *
     * @param list<string> $candidateKeys
     */
    public function cast(int $ballotId, int $userId, array $candidateKeys): void;

    /** Records the arithmetic without deciding: a single winner, or the tied keys. */
    public function tally(int $ballotId): BallotOutcome;

    /** Commits a winner and notifies whoever claims the purpose. */
    public function settle(int $ballotId, string $winningKey, ?int $settledByUserId = null): BallotOutcome;

    public function abandon(int $ballotId, ?int $abandonedByUserId = null): void;

    /** Null when the ballot does not exist or the viewer may not see it. */
    public function view(int $ballotId, ?int $viewerUserId): ?BallotView;

    /** @return list<BallotView> */
    public function listOpenFor(int $viewerUserId): array;

    /** @return list<BallotView> */
    public function listForSubject(BallotSubject $subject, ?int $viewerUserId): array;

    /**
     * Newest first, resolved ones included; the only way to reach a ballot that carries no subject.
     *
     * @return list<BallotView>
     */
    public function listForPurpose(string $purpose, ?int $viewerUserId): array;

    public function mayVote(int $ballotId, int $userId): bool;

    public function countOpenFor(int $viewerUserId): int;

    /**
     * Every ballot with its options, outcome and votes, oldest first, visibility ignored.
     *
     * @return list<PortableBallot>
     */
    public function exportAll(): array;

    /** Writes a ballot exactly as given, never tallying it and never notifying a listener. */
    public function restore(PortableBallot $ballot): int;
}
