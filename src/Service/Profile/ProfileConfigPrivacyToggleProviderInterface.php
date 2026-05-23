<?php declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ProfileConfigPrivacyToggleProviderInterface
{
    /**
     * Return a privacy toggle row to render on the profile config page,
     * or null when this provider has nothing to contribute for the given user.
     */
    public function getToggle(User $user): ?ProfileConfigPrivacyToggle;
}
