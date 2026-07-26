<?php declare(strict_types=1);

namespace App\Cms\ReservedSlug;

use App\Repository\CmsRepository;
use Override;

final readonly class LockedPageSlugProvider implements ReservedSlugProviderInterface
{
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
