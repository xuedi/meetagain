<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Settings;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConfigControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string CONFIG_PATH = '/en/admin/system/config';

    public function testConfigPageRendersWebsiteImageCard(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::CONFIG_PATH);

        // Assert
        $this->assertResponseIsSuccessful();
        $bodyText = $crawler->filter('body')->text();
        static::assertStringContainsString('Website image', $bodyText);
        static::assertStringContainsString('Recommended 1200 x 630', $bodyText);
        static::assertSame(
            1,
            $crawler->filter('input[type=file]')->count(),
            'Website image upload field should render',
        );
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
