<?php declare(strict_types=1);

namespace App\Cms\ReservedSlug;

use App\Repository\CmsRepository;
use Override;

/**
 * Reserves the slugs of locked pages so the editor cannot shadow them with a
 * new page.
 */
final readonly class LockedPageSlugProvider implements ReservedSlugProviderInterface
{
    /**
     * The home page slug rendered at the site root. It is never reserved: every
     * site needs its own home page, so the editor must be free to assign it.
     */
    private const string HOME_SLUG = 'index';

    public function __construct(
        private CmsRepository $cmsRepository,
    ) {}

    #[Override]
    public function getReservedSlugs(): iterable
    {
        foreach ($this->cmsRepository->findLockedSlugs() as $slug) {
            if ($slug === self::HOME_SLUG) {
                continue;
            }

            yield $slug;
        }
    }
}
