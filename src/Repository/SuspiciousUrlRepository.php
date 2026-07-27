<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SuspiciousUrl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuspiciousUrl>
 */
class SuspiciousUrlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuspiciousUrl::class);
    }

    public function findOneByUrl(string $url): ?SuspiciousUrl
    {
        return $this->findOneBy(['url' => $url]);
    }

    /**
     * @return list<SuspiciousUrl>
     */
    public function findAllOrdered(): array
    {
        return array_values($this->createQueryBuilder('s')->orderBy('s.url', 'ASC')->getQuery()->getResult());
    }

    /**
     * @return list<string>
     */
    public function findAllUrls(): array
    {
        $rows = $this->createQueryBuilder('s')->select('s.url')->getQuery()->getArrayResult();

        return array_values(array_map(static fn(array $row): string => (string) $row['url'], $rows));
    }
}
