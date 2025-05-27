<?php declare(strict_types=1);

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

    public function getWeekSummary(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        // TODO: remove custom stuff (doctrine.yaml::DoctrineExtensions\Query\Mysql\DateFormat) and find a upstream way
        //       also fill up dateRange in sql and return key value pair straight as array

        //$dbal = $this->getDoctrine()->getConnection();
        //$idsAndNames = $dbal->executeQuery('SELECT id, name FROM Categories')->fetchAll(\PDO::FETCH_KEY_PAIR);

        $unhydratedList = $this->getEntityManager()
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

        $list = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
        foreach ($unhydratedList as $item) {
            $list[$item['groupedDay']] = $item['number'];
        }

        return $list;
    }

    public function getTop100(): array
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id) as number', 'n.url')
            ->GroupBy('n.url')
            ->orderBy('number', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }
}
