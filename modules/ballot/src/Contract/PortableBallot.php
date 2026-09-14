<?php declare(strict_types=1);

namespace Module\Ballot\Contract;

use DateTimeImmutable;

final readonly class PortableBallot
{
    /**
     * @param list<Candidate> $candidates
     * @param array<int, list<string>> $votes
     * @param list<string> $tiedKeys
     */
    public function __construct(
        public string $purpose,
        public BallotStatus $status,
        public TallyMode $tallyMode,
        public SettlementMode $settlementMode,
        public DateTimeImmutable $deadline,
        public int $openedByUserId,
        public DateTimeImmutable $createdAt,
        public array $candidates,
        public array $votes = [],
        public ?string $title = null,
        public ?BallotSubject $subject = null,
        public ?string $winningKey = null,
        public array $tiedKeys = [],
        public ?int $settledByUserId = null,
        public ?DateTimeImmutable $settledAt = null,
    ) {}
}
