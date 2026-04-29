<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cms;
use App\Enum\MenuLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<Cms>
 */
class CmsRepository extends ServiceEntityRepository
{
    private const int CACHE_TTL = 3600;

    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(service: 'cache.cms_page_cache')]
        private readonly TagAwareCacheInterface $cache,
    ) {
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

        // Limit to 1 with a stable order so unfiltered queries pick the same page deterministically.
        $qb->orderBy('c.id', 'ASC')->setMaxResults(1);

        $results = $qb->getQuery()->getResult();

        return $results[0] ?? null;
    }

    /**
     * Find all published CMS pages for sitemap generation.
     *
     * @return array<Cms>
     */
    public function findPublished(): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.published = true')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
     * IDs are cached in Valkey; entities are fetched fresh on each request to avoid
     * serializing Doctrine proxy objects into the cache.
     *
     * @return array<Cms>
     */
    public function findByMenuLocation(MenuLocation $location): array
    {
        $ids = $this->cache->get('cms_menu_location_' . $location->value, function (ItemInterface $item) use (
            $location,
        ): array {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['cms_menu']);

            return $this
                ->createQueryBuilder('c')
                ->select('c.id')
                ->join('c.menuLocations', 'ml')
                ->where('ml.location = :location')
                ->andWhere('c.published = true')
                ->setParameter('location', $location)
                ->getQuery()
                ->getSingleColumnResult();
        });

        if ($ids === []) {
            return [];
        }

        return $this->findBy(['id' => $ids]);
    }

    /**
     * @return array<int>
     */
    public function getLockedCmsIds(): array
    {
        return $this->cache->get('cms_locked_ids', function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this
                ->createQueryBuilder('c')
                ->select('c.id')
                ->where('c.locked = true')
                ->getQuery()
                ->getSingleColumnResult();
        });
    }
}
