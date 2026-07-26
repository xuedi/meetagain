<?php declare(strict_types=1);

namespace Plugin\Voting\ValueObject;

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
