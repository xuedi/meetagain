<?php declare(strict_types=1);

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ImageBoxProviderInterface
{
    public function getPluginKey(): string;

    /** Rendered markup replacing the event page's image box, or null to leave core's in place. */
    public function renderImageBox(int $eventId): ?string;
}
