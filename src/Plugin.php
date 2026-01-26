<?php declare(strict_types=1);

namespace App;

use App\Entity\AdminSection;
use App\Entity\EventListItemTag;
use App\Entity\Link;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Plugin
{
    /**
     * Returns a unique identifier for the plugin.
     */
    public function getPluginKey(): string;

    /**
     * Returns list of links to be displayed in the top menu or the footer.
     *
     * @return list<Link>
     */
    public function getMenuLinks(): array;

    /**
     * Returns a rendered tile to be displayed as a box on the event details page.
     */
    public function getEventTile(int $eventId): ?string;

    /**
     * Runs fixture data creation after events have been extended.
     * Called by app:event:add-fixture command.
     */
    public function loadPostExtendFixtures(OutputInterface $output): void;

    /**
     * Returns admin sidebar section with links for this plugin.
     * Return null if the plugin has no admin links.
     */
    public function getAdminSystemLinks(): ?AdminSection;

    /**
     * Returns rendered HTML for the footer "about" section (copyright/branding area).
     * Return null if the plugin doesn't provide custom footer content.
     */
    public function getFooterAbout(): ?string;

    /**
     * Runs periodic maintenance/cron tasks for the plugin.
     * Called by app:cron command (typically every 5 minutes).
     */
    public function runCronTasks(OutputInterface $output): void;

    /**
     * Returns tags/badges to display for an event in list views.
     *
     * @return list<EventListItemTag>
     */
    public function getEventListItemTags(int $eventId): array;
}
