<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Logs;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotFoundLogControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(5, $crawler->filter('.tabs.is-boxed li')->count());
    }

    public function testIncidentColumnHeaderIsPresent(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        $headers = $crawler->filter('table thead th');
        $headerTexts = $headers->each(static fn($node) => trim($node->text()));
        static::assertContains('Incident', $headerTexts);
    }

    public function testTopHundredUrlTableIsGone(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(
            0,
            $crawler->filter('.container .column.is-4')->count(),
            'The top-100 sidebar column should be gone',
        );
        static::assertSame(1, $crawler->filter('.container table')->count(), 'Only one table should remain');
    }

    public function testGuestIsBlocked(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/logs/404');

        // Assert
        static::assertTrue($client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403);
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
