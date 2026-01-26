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

    /**
     * Find published CMS page by slug, optionally filtered by allowed IDs.
     *
     * @param string $slug The page slug
     * @param array<int>|null $allowedIds Allowed CMS IDs, or null for no filtering
     * @return Cms|null
     */
    public function findPublishedBySlug(string $slug, ?array $allowedIds = null): ?Cms
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->andWhere('c.published = true')
            ->setParameter('slug', $slug);

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return null; // No allowed IDs = no results
            }

            $qb->andWhere('c.id IN (:allowedIds)')
               ->setParameter('allowedIds', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
