<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Resolves the identity a message is sent under from the entity it is about. The first
 * implementation to return a non-null value wins; returning null defers to the next one, and to
 * the request-derived default when every implementation defers.
 */
#[AutoconfigureTag]
interface SendingIdentityProviderInterface
{
    public function resolve(?object $origin, string $locale): ?SendingIdentity;
}
