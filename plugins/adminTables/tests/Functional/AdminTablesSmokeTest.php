<?php declare(strict_types=1);

namespace Plugin\AdminTables\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke test for adminTables plugin routes.
 *
 * Tests CRUD admin interfaces for:
 * - Events
 * - Users
 * - Hosts
 * - Locations
 * - Images
 */
class AdminTablesSmokeTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testAdminTablesRoutesLoadForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $routes = $this->getAdminTablesRoutes();

        // Act & Assert
        foreach ($routes as $name => $path) {
            $client->request('GET', $path);
            $this->assertResponseIsSuccessful(sprintf(
                '%s (%s) should load successfully for admin',
                $name,
                $path,
            ));
        }
    }

    public function testAdminTablesRoutesRequireAuthentication(): void
    {
        // Arrange
        $client = static::createClient();

        $routes = $this->getAdminTablesRoutes();

        // Act & Assert
        foreach ($routes as $name => $path) {
            $client->request('GET', $path);
            $this->assertResponseRedirects();
        }
    }

    /**
     * Get all adminTables routes.
     *
     * @return array<string, string> Route name => path
     */
    private function getAdminTablesRoutes(): array
    {
        return [
            'Event List' => '/en/admin/event',
            'User List' => '/en/admin/user',
            'Host List' => '/en/admin/host',
            'Location List' => '/en/admin/location',
            'Image List' => '/en/admin/image',
        ];
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
