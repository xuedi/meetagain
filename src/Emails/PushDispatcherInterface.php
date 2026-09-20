<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lets a plugin notice that a message is on its way to a recipient, without core knowing how it is
 * delivered. Core ships no implementation: with none registered the iterable is empty and nothing
 * happens. Implementations must not throw and must not flush.
 */
#[AutoconfigureTag]
interface PushDispatcherInterface
{
    public function dispatch(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void;
}
