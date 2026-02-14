<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cms;
use App\Entity\MenuLocation;
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
        $qb = $this
            ->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->andWhere('c.published = true')
            ->setParameter('slug', $slug);

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return null; // No allowed IDs = no results
            }

            $qb->andWhere('c.id IN (:allowedIds)')->setParameter('allowedIds', $allowedIds);
        }

        // Order by ID and limit to 1 to handle duplicates gracefully
        // In multisite context, the filter should ensure only one result
        // Without filtering, we get the first available page
        $qb->orderBy('c.id', 'ASC')->setMaxResults(1);

        $results = $qb->getQuery()->getResult();

        return $results[0] ?? null;
    }

    /**
     * Find CMS pages by IDs, optionally filtered by allowed IDs.
     * If no IDs provided, returns all pages.
     *
     * @param array<int>|null $ids CMS IDs to fetch, or null for all pages
     * @return array<Cms>
     */
    public function findByIds(?array $ids): array
    {
        if ($ids === null) {
            return $this->findBy([], ['createdAt' => 'DESC']);
        }

        if ($ids === []) {
            return [];
        }

        return $this
            ->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<Cms>
     */
    public function findByMenuLocation(MenuLocation $location): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('JSON_CONTAINS(c.menuLocations, :location) = 1')
            ->andWhere('c.published = true')
            ->setParameter('location', json_encode($location->value))
            ->getQuery()
            ->getResult();
    }
}
