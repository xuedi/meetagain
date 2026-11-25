<?php declare(strict_types=1);

namespace Plugin\Fediverse\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Fediverse\Entity\Account;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findLiveForScope(?string $scopeId): ?Account
    {
        $qb = $this->createQueryBuilder('a')->where('a.retiredAt IS NULL')->setMaxResults(1);
        if ($scopeId === null) {
            $qb->andWhere('a.scopeId IS NULL');
        } else {
            $qb->andWhere('a.scopeId = :scopeId')->setParameter('scopeId', $scopeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByUsername(string $username): ?Account
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function isUsernameTakenOutsideScope(string $username, ?string $scopeId): bool
    {
        $account = $this->findOneByUsername($username);
        if (!$account instanceof Account) {
            return false;
        }

        return $account->isRetired() || $account->getScopeId() !== $scopeId;
    }

    /** @return list<Account> the scoped accounts first, the installation's last */
    public function findLive(): array
    {
        return $this
            ->createQueryBuilder('a')
            ->addSelect('CASE WHEN a.scopeId IS NULL THEN 1 ELSE 0 END AS HIDDEN installationLast')
            ->where('a.retiredAt IS NULL')
            ->orderBy('installationLast', SortDirection::Ascending)
            ->addOrderBy('a.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function findScopeIds(): array
    {
        return array_map(
            strval(...),
            $this->createQueryBuilder('a')->select('DISTINCT a.scopeId')->where('a.scopeId IS NOT NULL')->getQuery()->getSingleColumnResult(),
        );
    }

    public function countLive(): int
    {
        return (int) $this->createQueryBuilder('a')->select('COUNT(a.id)')->where('a.retiredAt IS NULL')->getQuery()->getSingleScalarResult();
    }
}
