<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Vote on whether the current user can see and use the Town Hall.
 *
 * Combined with AND logic: any false hides the topbar link and blocks the page.
 */
#[AutoconfigureTag]
interface TownHallAccessCheckerInterface
{
    public function canAccess(?User $user): bool;
}
