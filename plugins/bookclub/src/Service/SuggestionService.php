<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookSuggestion;
use Plugin\Bookclub\Entity\SuggestionStatus;
use Plugin\Bookclub\Repository\BookSuggestionRepository;
use RuntimeException;

readonly class SuggestionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookSuggestionRepository $suggestionRepo,
    ) {}

    public function suggest(Book $book, int $userId): BookSuggestion
    {
        $existingPending = $this->suggestionRepo->findUserPendingSuggestion($userId);
        if ($existingPending !== null) {
            throw new RuntimeException('You already have a pending suggestion. Withdraw it first to suggest another book.');
        }

        $suggestion = new BookSuggestion();
        $suggestion->setBook($book);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::Pending);
        $suggestion->setResubmitCount(0);

        $this->em->persist($suggestion);
        $this->em->flush();

        return $suggestion;
    }

    public function resubmit(int $suggestionId, int $userId): BookSuggestion
    {
        $oldSuggestion = $this->suggestionRepo->find($suggestionId);
        if ($oldSuggestion === null) {
            throw new RuntimeException('Suggestion not found');
        }

        if ($oldSuggestion->getSuggestedBy() !== $userId) {
            throw new RuntimeException('You can only resubmit your own suggestions');
        }

        if ($oldSuggestion->getStatus() !== SuggestionStatus::Rejected) {
            throw new RuntimeException('Only rejected suggestions can be resubmitted');
        }

        $existingPending = $this->suggestionRepo->findUserPendingSuggestion($userId);
        if ($existingPending !== null) {
            throw new RuntimeException('You already have a pending suggestion');
        }

        $suggestion = new BookSuggestion();
        $suggestion->setBook($oldSuggestion->getBook());
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::Pending);
        $suggestion->setResubmitCount($oldSuggestion->getResubmitCount() + 1);

        $oldSuggestion->setStatus(SuggestionStatus::Withdrawn);

        $this->em->persist($suggestion);
        $this->em->persist($oldSuggestion);
        $this->em->flush();

        return $suggestion;
    }

    public function withdraw(int $suggestionId, int $userId): void
    {
        $suggestion = $this->suggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw new RuntimeException('Suggestion not found');
        }

        if ($suggestion->getSuggestedBy() !== $userId) {
            throw new RuntimeException('You can only withdraw your own suggestions');
        }

        if ($suggestion->getStatus() !== SuggestionStatus::Pending) {
            throw new RuntimeException('Only pending suggestions can be withdrawn');
        }

        $suggestion->setStatus(SuggestionStatus::Withdrawn);
        $this->em->persist($suggestion);
        $this->em->flush();
    }

    public function getUserPendingSuggestion(int $userId): ?BookSuggestion
    {
        return $this->suggestionRepo->findUserPendingSuggestion($userId);
    }

    /** @return BookSuggestion[] */
    public function getPendingSuggestions(): array
    {
        return $this->suggestionRepo->findAllPending();
    }

    /** @return BookSuggestion[] */
    public function getPendingSuggestionsWithPriority(): array
    {
        $suggestions = $this->getPendingSuggestions();

        usort($suggestions, fn(BookSuggestion $a, BookSuggestion $b) =>
            $this->calculatePriority($b) <=> $this->calculatePriority($a)
        );

        return $suggestions;
    }

    public function calculatePriority(BookSuggestion $suggestion): float
    {
        $resubmitBonus = $suggestion->getResubmitCount() * 10;

        $suggestedAt = $suggestion->getSuggestedAt();
        $daysSinceSuggested = $suggestedAt !== null
            ? $suggestedAt->diff(new DateTimeImmutable())->days
            : 0;
        $timeBonus = $daysSinceSuggested * 1;

        return $resubmitBonus + $timeBonus;
    }

    public function get(int $id): ?BookSuggestion
    {
        return $this->suggestionRepo->find($id);
    }

    /** @return BookSuggestion[] */
    public function getUserRejectedSuggestions(int $userId): array
    {
        return $this->suggestionRepo->findBy([
            'suggestedBy' => $userId,
            'status' => SuggestionStatus::Rejected,
        ]);
    }
}
