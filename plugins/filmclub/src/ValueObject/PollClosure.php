<?php declare(strict_types=1);

namespace Plugin\Filmclub\ValueObject;

use Plugin\Filmclub\Entity\FilmSuggestion;

final readonly class PollClosure
{
    /**
     * @param FilmSuggestion[] $tiedSuggestions Non-empty only when the vote was a tie.
     */
    public function __construct(
        public readonly ?FilmSuggestion $winningSuggestion,
        public readonly array $tiedSuggestions,
    ) {}

    public function isTie(): bool
    {
        return $this->winningSuggestion === null && $this->tiedSuggestions !== [];
    }
}
