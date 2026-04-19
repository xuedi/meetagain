<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishImageSuggestion;

/** @extends ServiceEntityRepository<DishImageSuggestion> */
class DishImageSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DishImageSuggestion::class);
    }

    public function findByDish(Dish $dish): array
    {
        return $this->findBy(['dish' => $dish], ['createdAt' => 'ASC']);
    }

    public function countByDish(Dish $dish): int
    {
        return $this->count(['dish' => $dish]);
    }

    public function getLatestCreatedAt(): ?DateTimeImmutable
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.createdAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? new DateTimeImmutable((string) $result) : null;
    }

    /** @return Dish[] */
    public function findDishesWithPendingSuggestions(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT IDENTITY(s.dish) as dish_id')
            ->getQuery()
            ->getScalarResult();

        if ($rows === []) {
            return [];
        }

        $dishIds = array_column($rows, 'dish_id');

        return $this->getEntityManager()
            ->getRepository(Dish::class)
            ->findBy(['id' => $dishIds]);
    }
}
