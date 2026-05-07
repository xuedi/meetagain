<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Profile;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeveloperAppsControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/profile/developer-apps');
        $this->assertResponseRedirects();
    }

    public function testAuthenticatedUserCanAccessIndex(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/en/profile/developer-apps');
        $this->assertResponseIsSuccessful();
    }

    private function loginAdmin(KernelBrowser $client): void
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
