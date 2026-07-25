<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\Cms;
use App\Localization\AbstractLocalizedRowSource;
use Override;

abstract readonly class AbstractCmsLocalizedSource extends AbstractLocalizedRowSource
{
    #[Override]
    public function getOwnerType(): string
    {
        return self::OWNER_CMS;
    }

    /**
     * @param list<string> $keepLocales
     */
    protected function pageLabel(?Cms $page, array $keepLocales): string
    {
        if (!$page instanceof Cms) {
            return '';
        }

        foreach ($keepLocales as $locale) {
            $title = $page->getPageTitle($locale);
            if ($title !== null && $title !== '') {
                return $title;
            }
        }

        return (string) $page->getSlug();
    }
}
