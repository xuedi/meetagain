<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies that plugin admin navigation section keys merge correctly with core.
 */
class AdminNavigationSectionMergeTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    private function loginAsAdmin(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();

        return $client;
    }

    public function testSystemSectionAppearsExactlyOnce(): void
    {
        // Arrange
        $client = $this->loginAsAdmin();

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/system');
        $this->assertResponseIsSuccessful();

        // Assert - count sidebar section headings with text "System"
        $systemHeadings = $crawler->filter('aside.menu p.menu-label')->reduce(
            static fn($node): bool => trim($node->text()) === 'System',
        );

        static::assertCount(
            1,
            $systemHeadings,
            'Sidebar must show exactly one "System" section heading. ' .
            'Found: ' . $systemHeadings->count() . '. ' .
            'Duplicate sections indicate a plugin is using a raw English section string ' .
            'instead of the admin_shell.section_system translation key.',
        );
    }

    public function testContentSectionAppearsAtMostOnce(): void
    {
        // Arrange
        $client = $this->loginAsAdmin();

        // Act
        $crawler = $client->request('GET', '/en/admin/events');
        $this->assertResponseIsSuccessful();

        // Assert - the "Content" section heading must never appear twice. A plugin may move
        // its links into a different section, so the heading may also be absent.
        $contentHeadings = $crawler->filter('aside.menu p.menu-label')->reduce(
            static fn($node): bool => trim($node->text()) === 'Content',
        );

        static::assertLessThanOrEqual(
            1,
            $contentHeadings->count(),
            'Sidebar must show at most one "Content" section heading. ' .
            'Found: ' . $contentHeadings->count() . '. ' .
            'Duplicate sections indicate a plugin is using a raw English section string ' .
            'instead of the admin_shell.section_content translation key.',
        );
    }
}
