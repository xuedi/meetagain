<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\MenuLocation;
use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use App\ValueObject\MenuItem;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class MenuService
{
    public function __construct(
        private CmsRepository $cmsRepo,
        private CmsFilterService $cmsFilterService,
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

        // Get CMS pages with this menu location
        $cmsPages = $this->cmsRepo->findByMenuLocation($menuLocation);

        // Apply CMS filter (multisite filtering)
        $filterResult = $this->cmsFilterService->getCmsIdFilter();
        $allowedCmsIds = $filterResult->getCmsIds();

        $items = [];
        foreach ($cmsPages as $cms) {
            // Apply multisite filtering
            if ($allowedCmsIds !== null && !in_array($cms->getId(), $allowedCmsIds, true)) {
                continue;
            }

            $items[] = MenuItem::fromCms($cms, $locale);
        }

        return $items;
    }
}
