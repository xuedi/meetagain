<?php declare(strict_types=1);

namespace App\Filter\Member;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the members visible in the current context. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface MemberFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getUserIdFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isMemberAccessible(int $userId): ?bool;
}
