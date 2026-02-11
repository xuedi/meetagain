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

        // Test System route
        $client->request('GET', '/en/admin/system');
        $this->assertResponseIsSuccessful('System route should load for admin');

        // Test Email route
        $client->request('GET', '/en/admin/email');
        $this->assertResponseIsSuccessful('Email route should load for admin');

        // Test Translation route
        $client->request('GET', '/en/admin/translation');
        $this->assertResponseIsSuccessful('Translation route should load for admin');

        // Test Menu route
        $client->request('GET', '/en/admin/menu');
        $this->assertResponseIsSuccessful('Menu route should load for admin');

        // Test Announcements route
        $client->request('GET', '/en/admin/system/announcements');
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
        $client->request('GET', '/en/admin/email');
        $this->assertResponseRedirects();

        // Test Translation route
        $client->request('GET', '/en/admin/translation');
        $this->assertResponseRedirects();

        // Test Menu route
        $client->request('GET', '/en/admin/menu');
        $this->assertResponseRedirects();

        // Test Announcements route
        $client->request('GET', '/en/admin/system/announcements');
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
