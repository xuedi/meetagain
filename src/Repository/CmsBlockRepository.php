<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

/**
 * @extends ServiceEntityRepository<CmsBlock>
 */
class CmsBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsBlock::class);
    }

    public function getMaxPriority(): float
    {
        try {
            return $this->createQueryBuilder('b')->select('MAX(b.priority)')->getQuery()->getSingleScalarResult();
        } catch (Throwable) {
            return 1;
        }
    }

    /**
     * @return array<int>
     */
    public function findPageIdsWithType(CmsBlockType $type): array
    {
        $rows = $this
            ->createQueryBuilder('cb')
            ->select('IDENTITY(cb.page) as page_id')
            ->where('cb.type = :type')
            ->setParameter('type', $type)
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $row): int => (int) $row['page_id'], $rows);
    }

    /**
     * @return array<int, array<string, int>> Block count per language, keyed by page ID
     */
    public function countPerPageAndLanguage(): array
    {
        $rows = $this
            ->createQueryBuilder('cb')
            ->select('IDENTITY(cb.page) as page_id, cb.language as language, COUNT(cb.id) as block_count')
            ->groupBy('cb.page')
            ->addGroupBy('cb.language')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['page_id']][(string) $row['language']] = (int) $row['block_count'];
        }

        return $counts;
    }

    public function getBlocks(int $pageId, string $locale)
    {
        return $this
            ->createQueryBuilder('cb')
            ->select('cb')
            ->where('cb.page = :pageId')
            ->andWhere('cb.language = :locale')
            ->setParameter('pageId', $pageId)
            ->setParameter('locale', $locale)
            ->orderBy('cb.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
