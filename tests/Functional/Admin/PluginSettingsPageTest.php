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
        $client->request('GET', '/en/admin/plugin/glossary/settings');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testUnknownPluginReturns404(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', '/en/admin/plugin/does_not_exist/settings');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testPluginListLinksToThePerPluginSettingsPage(): void
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
            $crawler->filter('a[href$="/admin/plugin/glossary/settings"]')->count(),
            'Enabled plugins with settings should carry a configure link',
        );
    }

    public function testPluginWithoutSettingsShowsADisabledConfigureButton(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/plugin');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(
            0,
            $crawler->filter('a[href$="/admin/plugin/karaoke/settings"]')->count(),
            'A plugin without settings must not link anywhere',
        );
        static::assertGreaterThan(
            0,
            $crawler->filter('td span[title] > button[disabled] .fa-cog')->count(),
            'A plugin without settings must show a disabled cog carrying a hover explanation',
        );
    }

    public function testPostingWithUnknownSectionReturns404(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', '/en/admin/plugin/glossary/settings?section=does_not_exist');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testPostingASectionOfAnotherPluginReturns404(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', '/en/admin/plugin/glossary/settings?section=dishes');

        // Assert
        static::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testStewardCannotReachPage(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/plugin/glossary/settings');

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
