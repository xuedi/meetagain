<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface MemberViewSectionProviderInterface
{
    /**
     * Render an information section (info box, list, summary) shown below the
     * primary actions on the member view page, or null when this provider has
     * nothing to contribute for the given viewer/target pair.
     */
    public function renderSection(User $viewer, User $target): ?string;
}
