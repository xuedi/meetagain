<?php declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers a subsystem as a consumer of SecretBox-encrypted values.
 * Implementations appear in the admin overview at /admin/system/secretbox.
 */
#[AutoconfigureTag]
interface SecretBoxConsumerInterface
{
    /** Translation key for the subsystem label shown in the admin overview. */
    public function getKey(): string;

    /** Number of currently stored encrypted records for this subsystem. */
    public function count(): int;
}
