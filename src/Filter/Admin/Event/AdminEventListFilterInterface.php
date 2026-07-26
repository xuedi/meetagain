<?php declare(strict_types=1);

namespace App\Filter\Admin\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the events visible in the admin list. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface AdminEventListFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getEventIdFilter(): ?array;

    /**
     * @return bool|null null = no opinion, true = allow, false = deny
     */
    public function isEventAccessible(int $eventId): ?bool;

    /**
     * @return array<string, mixed>
     */
    public function getDebugContext(int $eventId): array;
}
