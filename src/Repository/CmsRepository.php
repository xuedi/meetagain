<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cms;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cms>
 */
class CmsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cms::class);
    }

    public function getChoices(): array
    {
        $all = $this->findAll();
        $list = [];
        foreach ($all as $cms) {
            $list[$cms->getSlug()] = $cms->getId();
        }

        return $list;
    }
}
