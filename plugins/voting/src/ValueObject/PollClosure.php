<?php declare(strict_types=1);

namespace Plugin\Voting\ValueObject;

/**
 * Outcome of closing a poll: either a single winning item id, or - on a vote tie - the set
 * of tied item ids awaiting a steward tie-break. Both empty means no votes were cast.
 */
final readonly class PollClosure
{
    /**
     * @param list<int> $tiedItemIds Non-empty only when the vote was a tie.
     */
    public function __construct(
        public ?int $winningItemId,
        public array $tiedItemIds,
    ) {}

    public function isTie(): bool
    {
        return $this->winningItemId === null && $this->tiedItemIds !== [];
    }
}
