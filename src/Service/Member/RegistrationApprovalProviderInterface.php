<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Decides whether an account that just confirmed its email address waits for approval, and names what it waits to
 * join for the reviewer. For each question the first implementation returning a non-null answer wins; without one,
 * the installation-wide automatic registration setting decides and the reviewer sees no label.
 */
#[AutoconfigureTag]
interface RegistrationApprovalProviderInterface
{
    public function requiresApproval(User $user): ?bool;

    public function waitingFor(User $user): ?string;
}
