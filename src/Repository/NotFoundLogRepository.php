<?php

namespace App\Repository;

use App\Entity\NotFoundLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotFoundLog>
 */
class NotFoundLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotFoundLog::class);
    }

    public function getWeekSummary(DateTimeImmutable $startDate, DateTimeImmutable $endDate, ): array
    {
        // TODO: remove custom stuff (doctrine.yaml::DoctrineExtensions\Query\Mysql\DateFormat) and find a upstream way
        //       also fill up dateRange in sql and return key value pair straight as array

        //$dbal = $this->getDoctrine()->getConnection();
        //$idsAndNames = $dbal->executeQuery('SELECT id, name FROM Categories')->fetchAll(\PDO::FETCH_KEY_PAIR);

        $unhydratedList = $this->getEntityManager('nf')
            ->createQueryBuilder()
            ->select('DATE_FORMAT(nf.createdAt, \'%W\') AS groupedDay', 'COUNT(nf.id) as number')
            ->from(NotFoundLog::class, 'nf')
            ->where('nf.createdAt > :startDate AND nf.createdAt < :endDate')
            ->groupBy('groupedDay')
            ->orderBy('nf.id')
            ->setParameter('startDate', $startDate) // 5 days
            ->setParameter('endDate', $endDate) // 5 days
            ->getQuery()
            ->getArrayResult();

        $list = array_fill_keys(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], 0);
        foreach ($unhydratedList as $item) {
            $list[$item['groupedDay']] = $item['number'];
        }

        return $list;
    }

    //    /**
    //     * @return NotFoundLog[] Returns an array of NotFoundLog objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?NotFoundLog
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
