<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookSuggestion;
use Plugin\Bookclub\Entity\SuggestionStatus;
use Plugin\Bookclub\Filter\BookGroupFilterService;
use Plugin\Bookclub\Repository\BookSuggestionRepository;
use RuntimeException;

readonly class SuggestionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookSuggestionRepository $suggestionRepo,
        private BookGroupFilterService $groupFilter,
        private EntityActionDispatcher $dispatcher,
    ) {}

    public function suggest(Book $book, int $userId): BookSuggestion
    {
        foreach ($this->suggestionRepo->findUserPendingSuggestions(
            $userId,
            $this->groupFilter->getAllowedSuggestionIds(),
        ) as $pending) {
            if ($pending->getBook()->getId() !== $book->getId()) {
                continue;
            }

            throw new RuntimeException('You have already suggested this book.');
        }

        $suggestion = new BookSuggestion();
        $suggestion->setBook($book);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setSuggestedAt(new DateTimeImmutable());
        $suggestion->setStatus(SuggestionStatus::Pending);
        $suggestion->setResubmitCount(0);

        $this->em->persist($suggestion);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::CreateBookSuggestion, $suggestion->getId());

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

        $this->dispatcher->dispatch(EntityAction::DeleteBookSuggestion, $suggestionId);
    }

    /** @return BookSuggestion[] */
    public function getUserPendingSuggestions(int $userId): array
    {
        return $this->suggestionRepo->findUserPendingSuggestions(
            $userId,
            $this->groupFilter->getAllowedSuggestionIds(),
        );
    }

    /** @return BookSuggestion[] */
    public function getPendingSuggestions(): array
    {
        return $this->suggestionRepo->findAllPending($this->groupFilter->getAllowedSuggestionIds());
    }

    /** @return BookSuggestion[] */
    public function getPendingSuggestionsWithPriority(): array
    {
        $suggestions = $this->getPendingSuggestions();

        usort(
            $suggestions,
            fn(BookSuggestion $a, BookSuggestion $b) => $this->calculatePriority($b) <=> $this->calculatePriority($a),
        );

        return $suggestions;
    }

    public function calculatePriority(BookSuggestion $suggestion): float
    {
        $resubmitBonus = $suggestion->getResubmitCount() * 10;

        $suggestedAt = $suggestion->getSuggestedAt();
        $daysSinceSuggested = $suggestedAt !== null ? $suggestedAt->diff(new DateTimeImmutable())->days : 0;
        $timeBonus = $daysSinceSuggested;

        return $resubmitBonus + $timeBonus;
    }

    public function get(int $id): ?BookSuggestion
    {
        return $this->suggestionRepo->find($id);
    }
}
