<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Announcement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * @return Announcement[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('a')->orderBy('a.createdAt', SortDirection::Descending)->getQuery()->getResult();
    }

    public function findByLinkHash(string $hash): ?Announcement
    {
        return $this->findOneBy(['linkHash' => $hash]);
    }

    public function findByCmsPage(int $cmsPageId): ?Announcement
    {
        return $this->findOneBy(['cmsPage' => $cmsPageId]);
    }
}
