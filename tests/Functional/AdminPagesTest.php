<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke test for core admin routes.
 *
 * This test verifies critical admin routes load successfully.
 * Plugin-specific routes should be tested in their respective plugin test folders.
 */
class AdminPagesTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testCoreAdminRoutesLoadForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Test CMS route
        $client->request('GET', '/en/admin/cms');
        $this->assertResponseIsSuccessful('CMS route should load for admin');

        // Test System route (redirects to /admin/system/config)
        $client->request('GET', '/en/admin/system');
        $this->assertResponseRedirects('/en/admin/system/config', 302);
        $client->followRedirect();
        $this->assertResponseIsSuccessful('System config route should load for admin');

        // Test Email route
        $client->request('GET', '/en/admin/email/templates');
        $this->assertResponseIsSuccessful('Email route should load for admin');

        // Test Translation route
        $client->request('GET', '/en/admin/translation');
        $this->assertResponseIsSuccessful('Translation route should load for admin');

        // Test Announcements route
        $client->request('GET', '/en/admin/email/announcements');
        $this->assertResponseIsSuccessful('Announcements route should load for admin');
    }

    public function testCoreAdminRoutesRequireAuthentication(): void
    {
        // Arrange
        $client = static::createClient();

        // Test CMS route
        $client->request('GET', '/en/admin/cms');
        $this->assertResponseRedirects();

        // Test System route
        $client->request('GET', '/en/admin/system');
        $this->assertResponseRedirects();

        // Test Email route
        $client->request('GET', '/en/admin/email/templates');
        $this->assertResponseRedirects();

        // Test Translation route
        $client->request('GET', '/en/admin/translation');
        $this->assertResponseRedirects();

        // Test Announcements route
        $client->request('GET', '/en/admin/email/announcements');
        $this->assertResponseRedirects();
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
