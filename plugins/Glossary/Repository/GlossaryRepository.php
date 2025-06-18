<?php declare(strict_types=1);

namespace Plugin\Glossary\Repository;

use Plugin\Glossary\Entity\Glossary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Glossary>
 */
class GlossaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Glossary::class);
    }
}
