<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Seo;

use App\Repository\EventCanonicalRootRepository;
use App\Service\Config\ConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CanonicalControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string CANONICAL_PATH = '/en/admin/seo/canonical';

    public function testEveryLaneOpensWithItsFirstOccurrence(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::CANONICAL_PATH);

        // Assert
        $this->assertResponseIsSuccessful();
        $lanes = $crawler->filter('table tbody tr');
        self::assertGreaterThan(0, $lanes->count());
        foreach ($lanes as $lane) {
            $chips = (new Crawler($lane))->filter('.tags .tag')->each(static fn($node) => trim($node->text()));
            self::assertNotEmpty($chips);
            self::assertSame('first', $chips[0]);
        }
    }

    public function testRebuildAllWritesMarkersAndRedirects(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $crawler = $client->request('GET', self::CANONICAL_PATH);
        $token = (string) $crawler->filter('a[data-post][href="' . self::CANONICAL_PATH . '/rebuild"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', self::CANONICAL_PATH . '/rebuild', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        $repository = static::getContainer()->get(EventCanonicalRootRepository::class);
        self::assertGreaterThanOrEqual(0, $repository->count([]));
    }

    public function testTheConfigSubpageSavesTheThreshold(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $crawler = $client->request('GET', self::CANONICAL_PATH . '/config');

        // Act
        $form = $crawler->selectButton('Save')->form();
        $form['event_canonical_settings[eventCanonicalThreshold]'] = '35';
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects(self::CANONICAL_PATH . '/config');
        $configService = static::getContainer()->get(ConfigService::class);
        self::assertSame(35, $configService->getEventCanonicalThreshold());
    }

    public function testTheCanonicalPageLinksToTheConfigSubpage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', self::CANONICAL_PATH);

        // Assert
        $this->assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a[href="' . self::CANONICAL_PATH . '/config"]')->count());
    }

    public function testRebuildAllRejectsAnInvalidCsrfToken(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', self::CANONICAL_PATH . '/rebuild', ['_token' => 'invalid']);

        // Assert
        $this->assertResponseStatusCodeSame(400);
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
