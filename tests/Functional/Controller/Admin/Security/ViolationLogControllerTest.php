<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ViolationLogControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testSubpageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/permissions/violations');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(4, $crawler->filter('.tabs.is-boxed li')->count());
        $headerTexts = $crawler->filter('table thead th')->each(static fn($node) => trim($node->text()));
        static::assertContains('Time', $headerTexts);
        static::assertContains('IP', $headerTexts);
        static::assertContains('URL', $headerTexts);
        static::assertContains('Reason', $headerTexts);
    }

    public function testPermissionsTabStaysActiveOnSubpage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/permissions/violations');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame('Permissions', trim($crawler->filter('.tabs.is-boxed li.is-active')->text()));
    }

    public function testPermissionsPageShowsViolationLogButton(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/permissions');

        // Assert
        $this->assertResponseIsSuccessful();
        $link = $crawler->filter('a[href*="/admin/security/permissions/violations"]');
        static::assertSame(1, $link->count());
        static::assertStringContainsString('Violation log', $link->text());
    }

    public function testGuestIsBlocked(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/security/permissions/violations');

        // Assert
        static::assertTrue($client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403);
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
