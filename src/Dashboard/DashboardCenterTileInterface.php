<?php declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DashboardCenterTileInterface
{
    /** Unique tile identifier */
    public function getKey(): string;

    /** Display priority (higher = shown first) */
    public function getPriority(): int;

    /** Check if user can see this tile */
    public function isAccessible(User $user, ?object $group): bool;

    /** Fetch tile data - includes year/week for time-series */
    public function getData(User $user, ?object $group, int $year, int $week): array;

    /** Template path */
    public function getTemplate(): string;
}
