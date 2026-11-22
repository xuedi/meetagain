<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DeletionBlockerInterface
{
    /**
     * A translation key naming why the account cannot be deleted yet, or null when this blocker has no objection.
     */
    public function blockReason(User $user): ?string;
}
