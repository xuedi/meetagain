<?php declare(strict_types=1);

namespace App\Circulation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Appends one tab to the circulation dashboard. The tab owns its own markup and
 * decides for itself whether it applies to the item type and context on screen.
 */
#[AutoconfigureTag]
interface DashboardTabInterface
{
    public function getKey(): string;

    public function getLabelKey(): string;

    public function getIcon(): string;

    public function supports(string $itemType, string $context): bool;

    public function render(string $itemType, string $context): string;

    /** Higher runs first. */
    public function getPriority(): int;
}
