<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmclubSettings;

/**
 * @extends ServiceEntityRepository<FilmclubSettings>
 */
class FilmclubSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmclubSettings::class);
    }

    public function save(FilmclubSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmclubSettings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findGlobal(): ?FilmclubSettings
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
