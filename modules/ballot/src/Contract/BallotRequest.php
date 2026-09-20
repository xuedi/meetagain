<?php declare(strict_types=1);

namespace Module\Ballot\Contract;

use DateTimeImmutable;

final readonly class BallotRequest
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        public string $purpose,
        public array $candidates,
        public DateTimeImmutable $deadline,
        public int $openedByUserId,
        public ?BallotSubject $subject = null,
        public TallyMode $tallyMode = TallyMode::Approval,
        public SettlementMode $settlementMode = SettlementMode::Automatic,
        public ?string $title = null,
    ) {}

    /**
     * @return list<string>
     */
    public function candidateKeys(): array
    {
        return array_values(array_unique(array_map(static fn(Candidate $candidate): string => $candidate->key, $this->candidates)));
    }
}
