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
        yield 'sitemap' => ['/en/admin/seo'];
        yield 'meta' => ['/en/admin/seo/meta'];
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

    #[DataProvider('seoRoutes')]
    public function testEveryTabSharesOneStripWithItselfMarkedActive(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', $path);

        // Assert: whichever tab is open, the strip is the same four and exactly one is current.
        $tabs = $crawler->filter('.tabs li');
        self::assertSame(4, $tabs->count());
        self::assertSame(1, $crawler->filter('.tabs li.is-active')->count());
        self::assertSame(
            $path,
            (string) $crawler->filter('.tabs li.is-active a')->attr('href'),
        );
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
