<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\BookSuggestion;
use Plugin\Bookclub\Entity\SuggestionStatus;

/**
 * @extends ServiceEntityRepository<BookSuggestion>
 */
class BookSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookSuggestion::class);
    }

    public function findUserPendingSuggestion(int $userId): ?BookSuggestion
    {
        return $this->findOneBy([
            'suggestedBy' => $userId,
            'status' => SuggestionStatus::Pending,
        ]);
    }

    /** @return BookSuggestion[] */
    public function findAllPending(): array
    {
        return $this->findBy(
            ['status' => SuggestionStatus::Pending],
            ['suggestedAt' => 'ASC']
        );
    }
}
