<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportMessage;
use App\Entity\SupportRequest;
use App\Enum\SupportMessageAuthor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupportMessage> */
class SupportMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportMessage::class);
    }

    /** @return SupportMessage[] */
    public function findThread(SupportRequest $request): array
    {
        return $this
            ->createQueryBuilder('sm')
            ->addSelect('au')
            ->leftJoin('sm.authorUser', 'au')
            ->where('sm.supportRequest = :request')
            ->setParameter('request', $request)
            ->orderBy('sm.createdAt', 'ASC')
            ->addOrderBy('sm.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countForRequest(SupportRequest $request): int
    {
        return (int) $this
            ->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->where('sm.supportRequest = :request')
            ->setParameter('request', $request)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTrailingRequesterMessages(SupportRequest $request): int
    {
        $authors = $this
            ->createQueryBuilder('sm')
            ->select('sm.author')
            ->where('sm.supportRequest = :request')
            ->setParameter('request', $request)
            ->orderBy('sm.createdAt', 'DESC')
            ->addOrderBy('sm.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $trailing = 0;
        foreach ($authors as $row) {
            if ($row['author'] !== SupportMessageAuthor::Requester) {
                break;
            }

            $trailing++;
        }

        return $trailing;
    }
}
