<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\MenuLocation;
use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use App\ValueObject\MenuItem;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class MenuService
{
    private const int CACHE_TTL = 3600;

    public function __construct(
        private CmsRepository $cmsRepo,
        private CmsFilterService $cmsFilterService,
        #[Autowire(service: 'cache.cms_page_cache')]
        private TagAwareCacheInterface $cache,
    ) {}

    /**
     * @return MenuItem[]
     */
    public function getMenuForContext(string $type, ?UserInterface $user, string $locale): array
    {
        // Map location string to MenuLocation enum
        $menuLocation = match ($type) {
            'top' => MenuLocation::TopBar,
            'col1' => MenuLocation::BottomCol1,
            'col2' => MenuLocation::BottomCol2,
            'col3' => MenuLocation::BottomCol3,
            'col4' => MenuLocation::BottomCol4,
            default => null,
        };

        if ($menuLocation === null) {
            return [];
        }

        // Apply CMS filter (multisite filtering) — memoized per request
        $filterResult = $this->cmsFilterService->getCmsIdFilter();
        $allowedCmsIds = $filterResult->getCmsIds();

        // Build cache key from type, locale and the set of allowed IDs
        $idHash = $allowedCmsIds !== null ? md5(implode(',', $allowedCmsIds)) : 'all';
        $cacheKey = sprintf('menu_%s_%s_%s', $type, $locale, $idHash);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $menuLocation,
            $allowedCmsIds,
            $locale,
        ): array {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['cms_menu']);

            $cmsPages = $this->cmsRepo->findByMenuLocation($menuLocation);

            $items = [];
            foreach ($cmsPages as $cms) {
                if ($allowedCmsIds !== null && !in_array($cms->getId(), $allowedCmsIds, true)) {
                    continue;
                }

                $items[] = MenuItem::fromCms($cms, $locale);
            }

            return $items;
        });
    }
}
