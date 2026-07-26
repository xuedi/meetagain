<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Seo;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitemapControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string SITEMAP_PATH = '/en/admin/seo';

    public function testSitemapPageRendersWithPublisherChainOutput(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::SITEMAP_PATH);

        // Assert
        $this->assertResponseIsSuccessful();
        $rowCount = $crawler->filter('#filteredTable tbody tr')->count();
        self::assertGreaterThan(0, $rowCount, 'Sitemap admin page should list at least static routes from the publisher chain');
        $bodyText = $crawler->filter('body')->text();
        self::assertStringContainsString('static', $bodyText, 'Static section badge from CoreSitemapPublisher should appear');
    }

    public function testSitemapSectionFilterNarrowsRows(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::SITEMAP_PATH . '?section=static');

        // Assert
        $this->assertResponseIsSuccessful();
        $sections = $crawler->filter('#filteredTable tbody tr td:first-child')->each(static fn($node) => trim($node->text()));
        self::assertNotEmpty($sections);
        foreach ($sections as $section) {
            self::assertSame('static', $section);
        }
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
