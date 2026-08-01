<?php declare(strict_types=1);

namespace Plugin\Photos\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Photos\Entity\PhotoTranslation;

/**
 * @extends ServiceEntityRepository<PhotoTranslation>
 */
class PhotoTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhotoTranslation::class);
    }
}
