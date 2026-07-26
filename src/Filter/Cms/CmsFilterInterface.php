<?php declare(strict_types=1);

namespace App\Filter\Cms;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the CMS pages visible in the current context. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface CmsFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getCmsIdFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isCmsAccessible(int $cmsId): ?bool;
}
