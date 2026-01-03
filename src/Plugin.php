<?php declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Plugin
{
    /**
     * Returns a unique identifier for the plugin
     */
    public function getPluginKey(): string;

    /**
     * returned list of links to be displayed in the top menu or the footer
     */
    public function getMenuLinks(): array;

    /**
     * Returns a rendered tile to be displayed as a box on the event details page
     */
    public function getEventTile(int $eventId): ?string;
}
