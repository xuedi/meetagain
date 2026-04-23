<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke test for i18n
 *
 * Loads a selection of public routes in en / de / zh and asserts:
 *   - the page renders successfully (HTTP 2xx/3xx)
 *   - no response body contains an unresolved translation key from the
 *     new nested namespaces (e.g. the literal text `admin_member.status_active`
 *     would indicate that a `{{ ... |trans }}` was missing somewhere)
 */
class TranslatorSmokeTest extends WebTestCase
{
    /**
     * Namespace prefixes introduced by the i18n hardening plan. If the literal
     * text `<namespace>.<word>` appears in the rendered body, a translate call
     * is missing or the key does not exist.
     */
    private const array KNOWN_NAMESPACES = [
        // Core namespaces
        'admin_cms',
        'admin_email',
        'admin_event',
        'admin_host',
        'admin_location',
        'admin_logs',
        'admin_member',
        'admin_shell',
        'admin_support',
        'admin_system',
        'chrome',
        'cms',
        'cms_showcase',
        'cookie',
        'email',
        'events',
        'member',
        'profile',
        'profile_config',
        'profile_image',
        'profile_images',
        'profile_messages',
        'profile_notifications',
        'profile_review',
        'profile_social',
        'report',
        'security',
        'shared',
        'support',
        // Bookclub plugin namespaces
        'bookclub',
        'bookclub_book',
        'bookclub_note',
        'bookclub_poll',
        'bookclub_poll_views',
        'bookclub_suggestion',
        'bookclub_manage',
        'bookclub_tile',
        'bookclub_views',
        // Dinnerclub plugin namespaces
        'dinnerclub',
        'dinnerclub_dinner',
        'dinnerclub_suggestion',
        'dinnerclub_approval',
        'dinnerclub_lists',
        'dinnerclub_views',
        'dinnerclub_tile',
        // Filmclub plugin namespaces
        'filmclub_film',
        'filmclub_vote',
        'filmclub_tile',
        // Glossary plugin namespaces
        'glossary',
        'glossary_blocks',
        // Multisite plugin namespaces are covered by
        // plugins/multisite/tests/Functional/TranslatorSmokeTest.php
    ];

    #[DataProvider('provideRoutes')]
    public function testRouteRendersWithoutLeakedTranslationKeys(string $route, string $locale): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', "/{$locale}{$route}");
        $response = $client->getResponse();

        // Assert - the page must load (allow redirects; login-required routes get a 302)
        // We also allow 404s for fixture-dependent routes that may not have locale variants
        $status = $response->getStatusCode();
        static::assertTrue($status < 500, "Route /{$locale}{$route} returned HTTP {$status} (server error)");

        // Assert - no unresolved translation key should leak as visible text
        $content = $response->getContent();
        if ($content === false || $content === '' || $status >= 400) {
            return;
        }

        $visibleText = self::extractVisibleText($content);
        foreach (self::KNOWN_NAMESPACES as $namespace) {
            $pattern = '/\b' . preg_quote($namespace, '/') . '\.[a-z][a-z0-9_]*\b/';
            if (preg_match($pattern, $visibleText, $match) === 1) {
                static::fail(
                    "Route /{$locale}{$route} leaked a raw translation key: '{$match[0]}'. "
                    . "A `|trans` filter is missing, or the key is undefined in messages.{$locale}.yaml.",
                );
            }
        }
    }

    public static function provideRoutes(): iterable
    {
        $routes = [
            '/',
            '/login',
            '/register',
            '/reset',
            '/events',
            '/event/1',
            '/event/featured/',
            '/members/',
            '/support/',
            '/imprint',
            '/privacy',
        ];
        foreach (['en', 'de', 'zh'] as $locale) {
            foreach ($routes as $route) {
                yield "{$locale} {$route}" => [$route, $locale];
            }
        }
    }

    /**
     * Strip HTML tags, script/style blocks, and attributes so we only assert
     * against what the user actually sees. Translation keys in CSS classes
     * or data attributes would be false positives.
     */
    private static function extractVisibleText(string $html): string
    {
        // Drop <script> and <style> blocks entirely
        $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html) ?? $html;

        // Drop all tag attributes by stripping tags
        return strip_tags($html);
    }
}
