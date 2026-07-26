<?php declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TranslatorSmokeTest extends WebTestCase
{
    private const array KNOWN_NAMESPACES = [
        'admin_cms',
        'admin_email',
        'admin_email_blocklist',
        'admin_email_debugging',
        'admin_email_planned',
        'admin_email_sendlog',
        'admin_email_templates',
        'admin_event',
        'admin_host',
        'admin_location',
        'admin_logs',
        'admin_seo',
        'admin_seo_canonical',
        'admin_seo_indexnow',
        'admin_seo_meta',
        'admin_seo_sitemap',
        'admin_member',
        'admin_shell',
        'admin_support',
        'admin_system',
        'admin_system_config',
        'admin_system_images',
        'admin_system_import',
        'admin_system_language',
        'admin_security_permissions',
        'admin_system_plugins',
        'admin_system_theme',
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
        'books',
        'books_book',
        'dishes',
        'dishes_dish',
        'films_film',
        'films_vote',
        'films_tile',
        'glossary',
    ];

    #[DataProvider('provideRoutes')]
    public function testRouteRendersWithoutLeakedTranslationKeys(string $route, string $locale): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', "/{$locale}{$route}");
        $response = $client->getResponse();

        // Assert
        $status = $response->getStatusCode();
        static::assertTrue($status < 500, "Route /{$locale}{$route} returned HTTP {$status} (server error)");

        // Assert
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

    private static function extractVisibleText(string $html): string
    {
        $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html) ?? $html;

        return strip_tags($html);
    }
}
