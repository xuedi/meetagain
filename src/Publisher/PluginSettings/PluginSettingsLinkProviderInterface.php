<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes a "Settings" button for a plugin. Hosts that render per-group plugin
 * admin pages iterate these providers, filter by which plugins are enabled in the
 * active group, and place one topbar button per match. The plugin owns its own
 * settings route and template; this interface only declares the button.
 */
#[AutoconfigureTag]
interface PluginSettingsLinkProviderInterface
{
    /** Plugin key (matches manifest / config/plugins.php). Used to gate the button on per-group activation. */
    public function getPluginKey(): string;

    /** Symfony route name the button links to. */
    public function getRoute(): string;

    /** Translation key for the button label. */
    public function getLabelKey(): string;

    /** Optional Font Awesome icon name (without the fa- prefix). */
    public function getIcon(): ?string;
}
