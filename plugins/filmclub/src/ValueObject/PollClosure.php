<?php declare(strict_types=1);

namespace Plugin\Filmclub\ValueObject;

use Plugin\Filmclub\Entity\Film;

final readonly class PollClosure
{
    /**
     * @param Film[] $tiedFilms Non-empty only when the vote was a tie.
     */
    public function __construct(
        public ?Film $winningFilm,
        public array $tiedFilms,
    ) {}

    public function isTie(): bool
    {
        return $this->winningFilm === null && $this->tiedFilms !== [];
    }
}
