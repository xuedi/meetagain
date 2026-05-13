<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\FilmPollVote;
use Plugin\Filmclub\Entity\FilmSelection;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Entity\PollStatus;
use Plugin\Filmclub\Entity\SuggestionStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Repository\FilmPollVoteRepository;
use Plugin\Filmclub\Repository\FilmSelectionRepository;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use Plugin\Filmclub\ValueObject\PollClosure;
use RuntimeException;

readonly class PollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilmPollRepository $pollRepo,
        private FilmPollVoteRepository $voteRepo,
        private FilmSuggestionRepository $suggestionRepo,
        private FilmSelectionRepository $selectionRepo,
        private WishlistService $wishlistService,
        private FilmGroupFilterService $groupFilter,
    ) {}

    /**
     * @param FilmSuggestion[] $suggestions
     */
    public function create(int $eventId, array $suggestions, DateTimeImmutable $endDate, int $createdBy): FilmPoll
    {
        if ($suggestions === []) {
            throw new RuntimeException('filmclub_poll.flash_no_suggestions');
        }

        $poll = new FilmPoll();
        $poll->setEventId($eventId);
        $poll->setCreatedBy($createdBy);
        $poll->setCreatedAt(new DateTimeImmutable());
        $poll->setEndDate($endDate);
        $poll->setStatus(PollStatus::Active);

        $this->em->persist($poll);

        foreach ($suggestions as $suggestion) {
            $suggestion->setPoll($poll);
            $suggestion->setStatus(SuggestionStatus::InPoll);
            $this->em->persist($suggestion);
        }

        $this->em->flush();

        return $poll;
    }

    /**
     * Replaces the user's full vote set in one transaction.
     *
     * @param FilmSuggestion[] $selectedSuggestions
     */
    public function castVote(int $userId, FilmPoll $poll, array $selectedSuggestions): void
    {
        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('filmclub_poll.flash_poll_closed');
        }

        $this->voteRepo->deleteByPollAndUser($poll->getId(), $userId);

        foreach ($selectedSuggestions as $suggestion) {
            $vote = new FilmPollVote();
            $vote->setPoll($poll);
            $vote->setUserId($userId);
            $vote->setSuggestion($suggestion);
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

        $voteCounts = $this->voteRepo->countVotesPerSuggestion($poll->getId());

        if ($voteCounts === []) {
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure(null, []);
        }

        $maxVotes = max($voteCounts);
        $leadingIds = array_keys(array_filter($voteCounts, static fn($c) => $c === $maxVotes));

        $suggestions = $poll->getSuggestions();
        $leadingSuggestions = array_values(array_filter(
            $suggestions->toArray(),
            static fn(FilmSuggestion $s) => in_array($s->getId(), $leadingIds, true),
        ));

        if (count($leadingSuggestions) === 1) {
            $winner = $leadingSuggestions[0];
            $poll->setWinningSuggestion($winner);
            $poll->setTiedSuggestions(null);
            $this->em->persist($poll);
            $this->em->flush();

            return new PollClosure($winner, []);
        }

        $poll->setWinningSuggestion(null);
        $poll->setTiedSuggestions(array_map(static fn($s) => $s->getId(), $leadingSuggestions));
        $this->em->persist($poll);
        $this->em->flush();

        return new PollClosure(null, $leadingSuggestions);
    }

    /**
     * Commits the outcome after a tie-break or clean single-winner close.
     * Writes FilmSelection, sets poll winningSuggestion, increments wishlist counters for losers.
     * This is the single point of counter mutation.
     */
    public function commitOutcome(FilmPoll $poll, FilmSuggestion $chosen): void
    {
        $film = $chosen->getFilm();
        if ($film === null) {
            throw new RuntimeException('filmclub_poll.flash_missing_film');
        }

        $selection = new FilmSelection();
        $selection->setFilm($film);
        $selection->setEventId($poll->getEventId());
        $selection->setSelectedBy($poll->getCreatedBy());
        $selection->setSelectedAt(new DateTimeImmutable());

        $chosen->setStatus(SuggestionStatus::Selected);

        $poll->setWinningSuggestion($chosen);

        $this->em->persist($selection);
        $this->em->persist($chosen);
        $this->em->persist($poll);
        $this->em->flush();

        $this->wishlistService->incrementForLosers($poll, $film);
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
        return $this->voteRepo->countVotesPerSuggestion($poll->getId());
    }

    /** @return FilmSuggestion[] */
    public function getPendingSuggestionsForPoll(): array
    {
        return $this->suggestionRepo->findAllPending($this->groupFilter->getAllowedSuggestionIds());
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
