<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Profile;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReviewControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/profile/review');
        $this->assertResponseRedirects();
    }

    public function testAuthenticatedUserCanAccessReviewPage(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/en/profile/review');
        $this->assertResponseIsSuccessful();
    }

    public function testApproveWithoutCsrfTokenShowsError(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('POST', '/en/profile/review/core.member_approval/approve/1', [
            '_token' => 'invalid-token',
        ]);

        $this->assertResponseRedirects('/en/profile/review');
        $client->followRedirect();
        $crawler = $client->getCrawler();
        static::assertGreaterThan(0, $crawler->filter('.notification.is-danger')->count());
    }

    public function testDenyWithoutCsrfTokenShowsError(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('POST', '/en/profile/review/core.member_approval/deny/1', [
            '_token' => 'invalid-token',
        ]);

        $this->assertResponseRedirects('/en/profile/review');
        $client->followRedirect();
        $crawler = $client->getCrawler();
        static::assertGreaterThan(0, $crawler->filter('.notification.is-danger')->count());
    }

    private function loginAdmin(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
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
