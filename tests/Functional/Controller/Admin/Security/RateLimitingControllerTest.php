<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RateLimitingControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/rate-limiting');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(3, $crawler->filter('.tabs.is-boxed li')->count());
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
