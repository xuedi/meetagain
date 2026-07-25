<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoAreaTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function seoRoutes(): iterable
    {
        yield 'dashboard' => ['/en/admin/seo'];
        yield 'meta' => ['/en/admin/seo/meta'];
        yield 'sitemap' => ['/en/admin/seo/sitemap'];
        yield 'indexnow' => ['/en/admin/seo/indexnow'];
        yield 'canonical' => ['/en/admin/seo/canonical'];
    }

    #[DataProvider('seoRoutes')]
    public function testEverySeoTabRendersForAdmin(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', $path);

        // Assert
        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('seoRoutes')]
    public function testEverySeoTabIsDeniedToAnonymous(string $path): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', $path);

        // Assert
        self::assertContains($client->getResponse()->getStatusCode(), [302, 401, 403]);
    }

    public function testSidebarLinksToTheSeoArea(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/seo');

        // Assert: the area contributes exactly one sidebar entry, marked active on its own pages.
        $links = $crawler->filter('aside.menu a[href="/en/admin/seo"]');
        self::assertSame(1, $links->count(), 'SEO must appear once in the sidebar');
        self::assertStringContainsString('is-active', (string) $links->attr('class'));
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
