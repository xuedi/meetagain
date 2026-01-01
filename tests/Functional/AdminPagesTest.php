<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminPagesTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testAdminDashboardPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/dashboard');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminLogsActivityPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/logs/activity');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminLogsSystemPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/logs/system');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminLogs404Page(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminEventListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/event/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminUserListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/user/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminLocationListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/location/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminHostListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/host/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminLanguageListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/language/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminImageListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/image/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminCmsListPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/cms/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminMenuPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/menu');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminSystemPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/system');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminPluginPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/plugin');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testAdminVisitorsPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/visitors/');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        // Act
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
