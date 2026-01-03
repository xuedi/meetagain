<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookPoll;
use Plugin\Bookclub\Entity\BookPollVote;
use Plugin\Bookclub\Entity\BookSuggestion;
use Plugin\Bookclub\Entity\PollStatus;
use Plugin\Bookclub\Entity\SuggestionStatus;
use Plugin\Bookclub\Repository\BookPollRepository;
use Plugin\Bookclub\Repository\BookPollVoteRepository;
use Plugin\Bookclub\Repository\BookRepository;
use Plugin\Bookclub\Repository\BookSuggestionRepository;
use RuntimeException;

readonly class PollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookPollRepository $pollRepo,
        private BookPollVoteRepository $voteRepo,
        private BookSuggestionRepository $suggestionRepo,
        private BookRepository $bookRepo,
    ) {}

    /**
     * @param int[] $suggestionIds Existing suggestion IDs to include
     * @param int[] $bookIds Book IDs to add directly (manager picks)
     */
    public function create(string $title, array $suggestionIds, array $bookIds, int $userId, ?int $eventId = null): BookPoll
    {
        $poll = new BookPoll();
        $poll->setTitle($title);
        $poll->setCreatedBy($userId);
        $poll->setCreatedAt(new DateTimeImmutable());
        $poll->setStatus(PollStatus::Draft);
        $poll->setEventId($eventId);

        $this->em->persist($poll);

        foreach ($suggestionIds as $suggestionId) {
            $suggestion = $this->suggestionRepo->find($suggestionId);
            if ($suggestion !== null && $suggestion->getStatus() === SuggestionStatus::Pending) {
                $suggestion->setPoll($poll);
                $suggestion->setStatus(SuggestionStatus::InPoll);
                $this->em->persist($suggestion);
            }
        }

        foreach ($bookIds as $bookId) {
            $book = $this->bookRepo->find($bookId);
            if ($book !== null) {
                $suggestion = new BookSuggestion();
                $suggestion->setBook($book);
                $suggestion->setSuggestedBy($userId);
                $suggestion->setSuggestedAt(new DateTimeImmutable());
                $suggestion->setStatus(SuggestionStatus::InPoll);
                $suggestion->setPoll($poll);
                $this->em->persist($suggestion);
            }
        }

        $this->em->flush();

        return $poll;
    }

    public function addBookToPoll(int $pollId, int $bookId, int $userId): void
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null) {
            throw new RuntimeException('Poll not found');
        }

        if ($poll->getStatus() !== PollStatus::Draft) {
            throw new RuntimeException('Can only add books to draft polls');
        }

        $book = $this->bookRepo->find($bookId);
        if ($book === null) {
            throw new RuntimeException('Book not found');
        }

        $suggestion = new BookSuggestion();
        $suggestion->setBook($book);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::InPoll);
        $suggestion->setPoll($poll);

        $this->em->persist($suggestion);
        $this->em->flush();
    }

    public function activate(int $pollId, ?DateTimeImmutable $endDate = null): void
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null) {
            throw new RuntimeException('Poll not found');
        }

        if ($poll->getStatus() !== PollStatus::Draft) {
            throw new RuntimeException('Only draft polls can be activated');
        }

        if ($poll->getSuggestions()->count() < 2) {
            throw new RuntimeException('Poll needs at least 2 options');
        }

        $poll->setStatus(PollStatus::Active);
        $poll->setStartDate(new DateTimeImmutable());
        $poll->setEndDate($endDate);

        $this->em->persist($poll);
        $this->em->flush();
    }

    public function vote(int $pollId, int $suggestionId, int $userId): void
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null || $poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('Poll not available for voting');
        }

        $suggestion = $this->suggestionRepo->find($suggestionId);
        if ($suggestion === null || $suggestion->getPoll()?->getId() !== $pollId) {
            throw new RuntimeException('Invalid suggestion for this poll');
        }

        $existingVote = $this->voteRepo->findUserVote($poll, $userId);

        if ($existingVote !== null) {
            $existingVote->setSuggestion($suggestion);
            $existingVote->setVotedAt(new DateTimeImmutable());
            $this->em->persist($existingVote);
        } else {
            $vote = new BookPollVote();
            $vote->setPoll($poll);
            $vote->setUserId($userId);
            $vote->setSuggestion($suggestion);
            $vote->setVotedAt(new DateTimeImmutable());
            $this->em->persist($vote);
        }

        $this->em->flush();
    }

    public function close(int $pollId): BookSuggestion
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null) {
            throw new RuntimeException('Poll not found');
        }

        if ($poll->getStatus() !== PollStatus::Active) {
            throw new RuntimeException('Only active polls can be closed');
        }

        $results = $this->getResults($pollId);
        $winner = $results['winner'];

        if ($winner === null) {
            throw new RuntimeException('No votes cast, cannot determine winner');
        }

        $poll->setStatus(PollStatus::Closed);
        $poll->setEndDate(new DateTimeImmutable());

        foreach ($poll->getSuggestions() as $suggestion) {
            if ($suggestion->getId() === $winner->getId()) {
                $suggestion->setStatus(SuggestionStatus::Selected);
            } else {
                $suggestion->setStatus(SuggestionStatus::Rejected);
            }
            $this->em->persist($suggestion);
        }

        $this->em->persist($poll);
        $this->em->flush();

        return $winner;
    }

    /**
     * @return array{poll: ?BookPoll, votes: array<int, int>, winner: ?BookSuggestion, totalVotes: int}
     */
    public function getResults(int $pollId): array
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null) {
            return ['poll' => null, 'votes' => [], 'winner' => null, 'totalVotes' => 0];
        }

        $voteCounts = $this->voteRepo->getVoteCounts($poll);

        $winner = null;
        $maxVotes = 0;
        foreach ($voteCounts as $suggestionId => $count) {
            if ($count > $maxVotes) {
                $maxVotes = $count;
                $winner = $this->suggestionRepo->find($suggestionId);
            }
        }

        return [
            'poll' => $poll,
            'votes' => $voteCounts,
            'winner' => $winner,
            'totalVotes' => array_sum($voteCounts),
        ];
    }

    public function getActivePoll(): ?BookPoll
    {
        return $this->pollRepo->findActivePoll();
    }

    public function getLatestClosedPoll(): ?BookPoll
    {
        return $this->pollRepo->findLatestClosed();
    }

    public function getUserVote(int $pollId, int $userId): ?BookPollVote
    {
        $poll = $this->pollRepo->find($pollId);
        if ($poll === null) {
            return null;
        }

        return $this->voteRepo->findUserVote($poll, $userId);
    }

    public function get(int $id): ?BookPoll
    {
        return $this->pollRepo->find($id);
    }

    /** @return BookPoll[] */
    public function getDraftPolls(): array
    {
        return $this->pollRepo->findBy(['status' => PollStatus::Draft], ['createdAt' => 'DESC']);
    }
}
