<?php declare(strict_types=1);

namespace Tests\Functional\Controller;

use App\Service\Config\ConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TownHallDashboardTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string DASHBOARD_PATH = '/en/townhall';
    private const string GALLERY_PATH = '/en/townhall/gallery';

    public function testTheDashboardIsHiddenWhileTheToggleIsOff(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->setTownHall('false');

        // Act
        $client->request('GET', self::DASHBOARD_PATH);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheGalleryIsHiddenWhileTheToggleIsOff(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->setTownHall('false');

        // Act
        $client->request('GET', self::GALLERY_PATH);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testAGuestIsSentToTheLogin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->setTownHall('true');

        // Act
        $client->request('GET', self::DASHBOARD_PATH);

        // Assert
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function setTownHall(string $value): void
    {
        static::getContainer()->get(ConfigService::class)->setString('show_town_hall', $value);
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
