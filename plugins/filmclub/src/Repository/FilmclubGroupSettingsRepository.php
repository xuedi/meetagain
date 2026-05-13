<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmclubGroupSettings;

/**
 * @extends ServiceEntityRepository<FilmclubGroupSettings>
 */
class FilmclubGroupSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmclubGroupSettings::class);
    }

    public function save(FilmclubGroupSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmclubGroupSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGroupId(int $groupId): ?FilmclubGroupSettings
    {
        return $this->findOneBy(['groupId' => $groupId]);
    }

    public function countWithEncryptedCredentials(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.encryptedTmdbKey IS NOT NULL OR s.encryptedOmdbKey IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
