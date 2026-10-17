<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppState;
use DateTimeImmutable;
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

    public function findValue(string $key): ?string
    {
        return $this->findValuesByKeys([$key])[$key] ?? null;
    }

    public function upsert(string $key, string $value, DateTimeImmutable $at): void
    {
        $this->write('ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', $key, $value, $at);
    }

    public function insertIfAbsent(string $key, string $value, DateTimeImmutable $at): void
    {
        $this->write('ON DUPLICATE KEY UPDATE key_name = key_name', $key, $value, $at);
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

        $rows = $this
            ->createQueryBuilder('appState')
            ->select('appState.keyName', 'appState.value')
            ->where('appState.keyName IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'value', 'keyName');
    }

    private function write(string $onDuplicate, string $key, string $value, DateTimeImmutable $at): void
    {
        $this
            ->getEntityManager()
            ->getConnection()
            ->executeStatement('INSERT INTO app_state (key_name, value, updated_at) VALUES (:key, :value, :at) ' . $onDuplicate, [
                'key' => $key,
                'value' => $value,
                'at' => $at->format('Y-m-d H:i:s'),
            ]);
    }
}
