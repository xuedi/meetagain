<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ThemeControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string GALLERY_PATH = '/en/admin/system/component-gallery';

    public function testGalleryRendersAllCategoriesWithoutFilter(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::GALLERY_PATH);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(
            15,
            $crawler->filter('.admin-section')->count(),
            'All gallery sections should render',
        );
    }

    public function testGalleryFilterByCategoryHidesOtherCategories(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::GALLERY_PATH . '?category=admin');

        // Assert
        $this->assertResponseIsSuccessful();
        $bodyText = $crawler->filter('body')->text();
        static::assertStringContainsString('Admin list table', $bodyText);
        static::assertStringNotContainsString('Sidebar (concept)', $bodyText);
    }

    public function testGalleryFilterByCategoryAndPageRendersSingleEntry(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::GALLERY_PATH . '?category=admin&page=admin_list');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(1, $crawler->filter('.admin-section')->count(), 'Exactly one entry should render');
    }

    public function testGalleryFallsBackForUnknownCategory(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::GALLERY_PATH . '?category=does-not-exist');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertGreaterThan(
            15,
            $crawler->filter('.admin-section')->count(),
            'Full gallery should render on unknown category',
        );
    }

    public function testGalleryFallsBackForUnknownPageWithinCategory(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::GALLERY_PATH . '?category=admin&page=does-not-exist');

        // Assert
        $this->assertResponseIsSuccessful();
        $bodyText = $crawler->filter('body')->text();
        static::assertStringContainsString('Admin list table', $bodyText);
        static::assertStringNotContainsString('Sidebar (concept)', $bodyText);
    }

    #[DataProvider('provideLocales')]
    public function testGalleryRendersWithoutLeakedTranslationKeys(string $locale): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', "/{$locale}/admin/system/component-gallery");

        // Assert
        $this->assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        $visible = strip_tags((string) preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $body));
        if (preg_match('/\badmin_system_gallery\.[a-z][a-z0-9_]*\b/', $visible, $match) === 1) {
            static::fail("Gallery leaked a raw translation key in {$locale}: '{$match[0]}'");
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideLocales(): iterable
    {
        yield 'en' => ['en'];
        yield 'de' => ['de'];
        yield 'zh' => ['zh'];
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
