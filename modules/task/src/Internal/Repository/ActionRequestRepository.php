<?php declare(strict_types=1);

namespace Module\Task\Internal\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Task\Internal\Action\RequestState;
use Module\Task\Internal\Entity\ActionRequest;

/**
 * @extends ServiceEntityRepository<ActionRequest>
 */
class ActionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionRequest::class);
    }

    public function save(ActionRequest $request): void
    {
        $em = $this->getEntityManager();
        $em->persist($request);
        $em->flush();
    }

    public function hasAnotherUnderway(ActionRequest $request, DateTimeImmutable $now): bool
    {
        return $this
            ->createQueryBuilder('r')
            ->select('r.id')
            ->where('r.identifier = :identifier')
            ->andWhere('r.id <> :id')
            ->andWhere('r.startedAt IS NOT NULL')
            ->andWhere('r.state = :pending OR r.leaseUntil >= :now')
            ->setParameter('identifier', $request->getIdentifier())
            ->setParameter('id', $request->getId())
            ->setParameter('pending', RequestState::Pending->value)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult() !== [];
    }

    public function reattach(ActionRequest $request): ActionRequest
    {
        return $this->getEntityManager()->contains($request) ? $request : $this->find($request->getId()) ?? $request;
    }

    public function refresh(ActionRequest $request): void
    {
        $this->getEntityManager()->refresh($request);
    }

    /**
     * @return list<int>
     */
    public function findPendingIdsCreatedBefore(DateTimeImmutable $cutoff): array
    {
        $rows = $this
            ->createQueryBuilder('r')
            ->select('r.id')
            ->where('r.state = :pending')
            ->andWhere('r.createdAt < :cutoff')
            ->setParameter('pending', RequestState::Pending->value)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'id'));
    }

    /**
     * @return list<ActionRequest>
     */
    public function findLeaseExpired(DateTimeImmutable $now): array
    {
        return $this
            ->createQueryBuilder('r')
            ->where('r.state = :running')
            ->andWhere('r.leaseUntil < :now')
            ->setParameter('running', RequestState::Running->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ActionRequest>
     */
    public function findRecent(string $identifier, int $limit): array
    {
        return $this->findBy(['identifier' => $identifier], ['id' => 'DESC'], $limit);
    }

    /**
     * @return array<string, ActionRequest> the newest request per action
     */
    public function findLatestPerIdentifier(): array
    {
        $latestIds = $this->createQueryBuilder('latest')->select('MAX(latest.id)')->groupBy('latest.identifier');
        $requests = $this->createQueryBuilder('r')->where(sprintf('r.id IN (%s)', $latestIds->getDQL()))->getQuery()->getResult();

        $byIdentifier = [];
        foreach ($requests as $request) {
            $byIdentifier[$request->getIdentifier()] = $request;
        }

        return $byIdentifier;
    }
}
