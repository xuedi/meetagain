<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\User;
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
        return $qb
            ->select('i')
            ->from(Image::class, 'i')
            ->where($qb->expr()->isNotNull('i.updatedAt'))
            ->andWhere($qb->expr()->lt('i.updatedAt', ':date'))
            ->setParameter('date', new DateTime('-' . $int . ' days'))
            ->getQuery()
            ->getResult();
    }

    public function getEventList(User $user): array
    {
        $result = $this->createQueryBuilder('i')
            ->leftJoin('i.event', 'e')
            ->addSelect('e')
            ->where('i.uploader = :user')
            ->andWhere('i.event IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('i.event')
            ->getQuery()
            ->getResult();

        $return = [];
        foreach ($result as $item) {
            $event = $item->getEvent();
            $return[] = [
                'id' => $event->getId(),
                'date' => $event->getStart()->format('Y-m-d'),
            ];
        }
        return $return;
    }

    public function getFileList(): array
    {
        $list = [];
        foreach ($this->findAll() as $image) {
            $list[$image->getHash()] = $image->getType();
        }

        return $list;
    }
}
