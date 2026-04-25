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
}
