<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemTag>
 */
class ItemTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemTag::class);
    }

    /** @return list<ItemTag> */
    public function findForType(string $itemType): array
    {
        return array_values($this->createQueryBuilder('t')
            ->where('t.itemType = :type')->setParameter('type', $itemType)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult());
    }

    public function findOneForType(string $itemType, int $id): ?ItemTag
    {
        return $this->findOneBy(['itemType' => $itemType, 'id' => $id]);
    }

    public function nextPosition(string $itemType): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.itemType = :type')->setParameter('type', $itemType)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /** @return list<int> */
    public function idsForType(string $itemType): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.itemType = :type')->setParameter('type', $itemType)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'id'));
    }

    /** @return list<int> the ids of every tag below the given one, deepest branch included */
    public function descendantIds(ItemTag $tag): array
    {
        $descendants = [];
        $frontier = [$tag->getId()];

        while ($frontier !== []) {
            $rows = $this->createQueryBuilder('t')
                ->select('t.id')
                ->where('t.parent IN (:parents)')->setParameter('parents', $frontier)
                ->getQuery()
                ->getScalarResult();

            $frontier = array_values(array_diff(array_map('intval', array_column($rows, 'id')), $descendants));
            $descendants = [...$descendants, ...$frontier];
        }

        return $descendants;
    }
}
