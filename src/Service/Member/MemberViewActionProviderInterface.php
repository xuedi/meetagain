<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface MemberViewActionProviderInterface
{
    /**
     * Render an action block (button, dropdown, etc.) shown on the member view page,
     * or null when this provider has nothing to contribute for the given viewer/target pair.
     */
    public function renderActions(User $viewer, User $target): ?string;
}
