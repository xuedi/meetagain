<?php declare(strict_types=1);

namespace App\Security\Permission;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implementations promise to decide a subset of permission attributes.
 * Plugins claim domains by returning true from supports() for the attributes they own.
 */
#[AutoconfigureTag]
interface PermissionCheckerInterface
{
    public function supports(string $attribute, mixed $subject): bool;

    /**
     * Return true to allow, false to deny, or null to abstain.
     */
    public function vote(string $attribute, PermissionContext $context): ?bool;
}
