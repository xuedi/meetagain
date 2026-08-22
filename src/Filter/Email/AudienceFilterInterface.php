<?php declare(strict_types=1);

namespace App\Filter\Email;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the recipient list of mail addressed to the installation as a whole. Implementations may
 * only remove recipients, never add them, and the list passes through untouched when none is
 * registered.
 */
#[AutoconfigureTag]
interface AudienceFilterInterface
{
    /**
     * @param list<User> $recipients
     * @return list<User>
     */
    public function filterInstallationWideAudience(array $recipients): array;
}
