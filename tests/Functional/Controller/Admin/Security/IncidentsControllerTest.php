<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class IncidentsControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/incidents');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(3, $crawler->filter('.tabs.is-boxed li')->count(), 'Three security tabs should be rendered');
    }

    public function testOldUrlProbingRouteIsGone(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/security/url-probing');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
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
