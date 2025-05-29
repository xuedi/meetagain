<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function getOldImageUpdates(int $int): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        return $qb->select('i')
            ->from(Image::class, 'i')
            ->where($qb->expr()->isNotNull('i.updatedAt'))
            ->andWhere($qb->expr()->lt('i.updatedAt', ':date'))
            ->setParameter('date', new DateTime('-'.$int.' days'))
            ->getQuery()
            ->getResult();
    }
}
