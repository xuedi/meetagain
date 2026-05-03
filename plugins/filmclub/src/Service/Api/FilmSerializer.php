<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service\Api;

use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmGenre;
use Plugin\Filmclub\Entity\Vote;

readonly class FilmSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toSummary(Film $film): array
    {
        return [
            'id' => $film->getId(),
            'title' => $film->getTitle(),
            'year' => $film->getYear(),
            'runtime' => $film->getRuntime(),
            'genres' => array_map(static fn(FilmGenre $g) => $g->name, $film->getGenres()),
            'createdAt' => $film->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetail(Film $film, ?Vote $latestVote): array
    {
        return [
            ...$this->toSummary($film),
            'latestVote' => $latestVote !== null ? $this->serializeVote($latestVote, $film) : null,
        ];
    }

    /**
     * Tally is exposed only after the vote closes; live tallies could sway in-progress voting.
     *
     * @return array<string, mixed>
     */
    private function serializeVote(Vote $vote, Film $film): array
    {
        $closed = $vote->isClosed() || ($vote->getClosesAt()?->getTimestamp() ?? PHP_INT_MAX) <= time();
        $tally = null;
        $totalBallots = $vote->getBallots()->count();
        if ($closed) {
            $count = 0;
            foreach ($vote->getBallots() as $ballot) {
                if ($ballot->getFilm()?->getId() === $film->getId()) {
                    ++$count;
                }
            }
            $tally = $count;
        }

        return [
            'voteId' => $vote->getId(),
            'eventId' => $vote->getEventId(),
            'isOpen' => !$closed,
            'closesAt' => $vote->getClosesAt()?->format(DATE_ATOM),
            'totalBallots' => $totalBallots,
            'tallyForFilm' => $tally,
        ];
    }
}
