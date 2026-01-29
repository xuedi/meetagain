<?php declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DashboardSideTileInterface
{
    /** Unique tile identifier */
    public function getKey(): string;

    /** Display priority (higher = shown first) */
    public function getPriority(): int;

    /** Check if user can see this tile */
    public function isAccessible(User $user): bool;

    /** Fetch tile data - no time parameters */
    public function getData(User $user): array;

    /** Template path */
    public function getTemplate(): string;
}
