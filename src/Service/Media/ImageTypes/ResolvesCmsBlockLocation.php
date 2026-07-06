<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Repository\CmsBlockRepository;

/**
 * Shared locate() for the three CMS image types, whose usages all resolve to a CMS block on a page.
 */
trait ResolvesCmsBlockLocation
{
    abstract protected function cmsBlockRepository(): CmsBlockRepository;

    public function locate(Image $image): ?array
    {
        $block = $this->cmsBlockRepository()->findOneBy(['image' => $image]);
        if ($block === null || $block->getPage() === null) {
            return null;
        }

        return [
            'label' => sprintf('CMS block on page #%d', $block->getPage()->getId()),
            'route' => 'app_admin_cms_edit',
            'params' => ['id' => $block->getPage()->getId()],
        ];
    }
}
