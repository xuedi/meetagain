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
}
