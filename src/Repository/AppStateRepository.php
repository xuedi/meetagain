<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppState> */
class AppStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppState::class);
    }

    public function findByKey(string $key): ?AppState
    {
        return $this->findOneBy(['keyName' => $key]);
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function findValuesByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('appState')
            ->select('appState.keyName', 'appState.value')
            ->where('appState.keyName IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'value', 'keyName');
    }
}
