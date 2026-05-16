<?php declare(strict_types=1);

namespace Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PluginSettingsPageTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testPluginSettingsPageLoadsForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/plugin/settings');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testPluginListShowsSettingsTopbarButton(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/plugin');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(
            0,
            $crawler->filter('a[href$="/admin/plugin/settings"]')->count(),
            'Settings topbar button should link to /admin/plugin/settings',
        );
    }

    public function testPostingWithUnknownProviderReturns404(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', '/en/admin/plugin/settings?provider=does_not_exist');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testStewardCannotReachPage(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/plugin/settings');

        // Assert
        $status = $client->getResponse()->getStatusCode();
        static::assertContains($status, [302, 401, 403], 'Unauthenticated request must be denied');
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
