<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PluginExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_plugins_links', [PluginRuntime::class, 'getPluginsLinks']),
            new TwigFunction('get_leading_plugin_links', [PluginRuntime::class, 'getLeadingPluginLinks']),
            new TwigFunction('get_plugin_stylesheets', [PluginRuntime::class, 'getPluginStylesheets']),
            new TwigFunction('get_plugin_javascripts', [PluginRuntime::class, 'getPluginJavascripts']),
            new TwigFunction('get_plugin_footer_about', [PluginRuntime::class, 'getPluginFooterAbout']),
            new TwigFunction('get_plugin_footer_links', [PluginRuntime::class, 'getPluginFooterLinks']),
            new TwigFunction('get_plugin_profile_dropdown_links', [
                PluginRuntime::class,
                'getPluginProfileDropdownLinks',
            ]),
            new TwigFunction('get_plugin_profile_config_links', [PluginRuntime::class, 'getPluginProfileConfigLinks']),
            new TwigFunction('get_plugin_navbar_pills_html', [PluginRuntime::class, 'getPluginNavbarPillsHtml'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('event_list_item_tags', [PluginRuntime::class, 'getEventListItemTags'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('warm_event_list_item_tags', [PluginRuntime::class, 'warmEventListItemTags']),
        ];
    }
}
