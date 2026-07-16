<?php declare(strict_types=1);

namespace Plugin\Films\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Films\Entity\FilmsSettings;

/**
 * @extends ServiceEntityRepository<FilmsSettings>
 */
class FilmsSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmsSettings::class);
    }

    public function save(FilmsSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmsSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findGlobal(): ?FilmsSettings
    {
        return $this->createQueryBuilder('s')->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function countWithEncryptedCredentials(): int
    {
        return (int) $this
            ->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.encryptedTmdbKey IS NOT NULL OR s.encryptedOmdbKey IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
