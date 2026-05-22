<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\FilmPollVote;
use Plugin\Filmclub\Entity\FilmSelection;
use Plugin\Filmclub\Entity\PollStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Repository\FilmPollVoteRepository;
use Plugin\Filmclub\ValueObject\PollClosure;
use RuntimeException;

readonly class PollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmPollRepository $pollRepo,
        private FilmPollVoteRepository $voteRepo,
        private WishlistService $wishlistService,
        private FilmGroupFilterService $groupFilter,
    ) {}

    /**
     * @param Film[] $films
     */
    public function create(Event $event, array $films, int $durationDays, int $createdBy): FilmPoll
    {
        if ($films === []) {
            throw new RuntimeException('filmclub_poll.flash_no_films');
        }

        $createdAt = new DateTimeImmutable();
        $poll = new FilmPoll();
        $poll->setEvent($event);
        $poll->setCreatedBy($createdBy);
        $poll->setCreatedAt($createdAt);
        $poll->setDurationDays($durationDays);
        $poll->setEndDate($createdAt->modify('+' . $durationDays . ' days'));
        $poll->setStatus(PollStatus::Active);

        foreach ($films as $film) {
            $poll->addFilm($film);
        }

        $this->em->persist($poll);
        $this->em->flush();

        return $poll;
    }

    /**
     * Replaces the user's full vote set in one transaction.
     *
     * @param Film[] $selectedFilms
     */
    public function castVote(int $userId, FilmPoll $poll, array $selectedFilms): void
    {
        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('filmclub_poll.flash_poll_closed');
        }

        $this->voteRepo->deleteByPollAndUser($poll->getId(), $userId);

        foreach ($selectedFilms as $film) {
            $vote = new FilmPollVote();
            $vote->setPoll($poll);
            $vote->setUserId($userId);
            $vote->setFilm($film);
            $vote->setVotedAt(new DateTimeImmutable());
            $this->em->persist($vote);
        }

        $this->em->flush();
    }

    public function close(FilmPoll $poll): PollClosure
    {
        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('filmclub_poll.flash_already_closed');
        }

        $poll->setStatus(PollStatus::Closed);
        $poll->setClosedAt(new DateTimeImmutable());

        $voteCounts = $this->voteRepo->countVotesPerFilm($poll->getId());

        if ($voteCounts === []) {
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure(null, []);
        }

        $maxVotes = max($voteCounts);
        $leadingFilmIds = array_keys(array_filter($voteCounts, static fn($c) => $c === $maxVotes));

        $films = $poll->getFilms();
        $leadingFilms = array_values(array_filter($films->toArray(), static fn(Film $f) => in_array(
            $f->getId(),
            $leadingFilmIds,
            true,
        )));

        if (count($leadingFilms) === 1) {
            $winner = $leadingFilms[0];
            $poll->setWinningFilm($winner);
            $poll->setTiedFilmIds(null);
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure($winner, []);
        }

        $poll->setWinningFilm(null);
        $poll->setTiedFilmIds(array_map(static fn(Film $f) => $f->getId(), $leadingFilms));
        $this->em->persist($poll);
        $this->em->flush();

        return new PollClosure(null, $leadingFilms);
    }

    /**
     * Commits the outcome after a tie-break or clean single-winner close.
     * Writes FilmSelection, sets poll winningFilm, calls group-wide wishlist outcome.
     */
    public function commitOutcome(FilmPoll $poll, Film $chosen): void
    {
        $selection = new FilmSelection();
        $selection->setFilm($chosen);
        $selection->setEventId($poll->getEventId());
        $selection->setSelectedBy($poll->getCreatedBy());
        $selection->setSelectedAt(new DateTimeImmutable());

        $poll->setWinningFilm($chosen);

        $this->em->persist($selection);
        $this->em->persist($poll);
        $this->em->flush();

        $this->wishlistService->onPollOutcome($chosen);
    }

    /** @return FilmPoll[] */
    public function getActivePolls(): array
    {
        return $this->pollRepo->findActive($this->groupFilter->getAllowedPollIds());
    }

    /** @return FilmPoll[] */
    public function getClosedPolls(): array
    {
        return $this->pollRepo->findClosed($this->groupFilter->getAllowedPollIds());
    }

    public function get(int $id): ?FilmPoll
    {
        return $this->pollRepo->find($id);
    }

    public function getVoteCounts(FilmPoll $poll): array
    {
        return $this->voteRepo->countVotesPerFilm($poll->getId());
    }

    public function hasUserVoted(FilmPoll $poll, int $userId): bool
    {
        return $this->voteRepo->hasUserVoted($poll->getId(), $userId);
    }

    /** @return FilmPollVote[] */
    public function getUserVotes(FilmPoll $poll, int $userId): array
    {
        return $this->voteRepo->findByPollAndUser($poll->getId(), $userId);
    }
}
