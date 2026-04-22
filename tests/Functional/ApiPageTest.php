<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for /api/ - the human-readable API documentation page that also
 * serves as the discovery surface for the meetagain-toolbox repo.
 */
class ApiPageTest extends WebTestCase
{
    public function testApiPageRendersWithToolboxSidebar(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/');
        $html = (string) $client->getResponse()->getContent();

        // Assert
        $this->assertResponseIsSuccessful('/api/ should return HTTP 200');
        static::assertStringContainsString('api-toolbox-sidebar', $html, 'Toolbox sidebar component should render');
        static::assertStringContainsString('https://github.com/xuedi/meetagain-toolbox', $html, 'Sidebar should link to the toolbox repo');
        static::assertStringContainsString('@meetagain/mcp-server', $html, 'Sidebar should show the MCP server install snippet');
    }

    public function testApiPageLoadsApiDocsScriptExactlyOnce(): void
    {
        // Arrange — regression guard: a previous nesting bug had api-docs.js
        // emitted twice (once via the parent javascripts slot in <head>, once
        // literally inside <body>), causing the toggle handler to bind twice.
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/');
        $html = (string) $client->getResponse()->getContent();

        // Assert
        $occurrences = preg_match_all('#<script[^>]+src="[^"]*api-docs[^"]*\.js"#', $html);
        static::assertSame(1, $occurrences, 'api-docs.js must be loaded exactly once');
    }

    public function testApiPageStillListsGroupsEndpointsViaPluginFragment(): void
    {
        // Arrange — /api/groups* is not in core's openapi.json anymore;
        // it lives in the multisite plugin's getOpenApiFragment(). The page
        // must still render those endpoints, proving the merger wired through.
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/');
        $html = (string) $client->getResponse()->getContent();

        // Assert
        static::assertStringContainsString('/api/groups', $html, 'Multisite groups endpoint must be merged into page');
        static::assertStringContainsString('/api/groups/{slug}', $html, 'Multisite group-detail endpoint must be merged into page');
    }

    public function testOpenApiSpecMergesMultisitePathsAndSchema(): void
    {
        // Arrange — the JSON spec served at /api/openapi.json must include the
        // multisite-contributed paths and the Group schema, even though core's
        // config/api/openapi.json no longer mentions them.
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/openapi.json');
        $payload = json_decode((string) $client->getResponse()->getContent(), true);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertIsArray($payload);
        static::assertArrayHasKey('/api/groups', $payload['paths'], 'Multisite paths must appear in served spec');
        static::assertArrayHasKey('/api/groups/{slug}', $payload['paths']);
        static::assertArrayHasKey('Group', $payload['components']['schemas'], 'Multisite Group schema must appear in served spec');
    }
}
