<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Logs;

use App\Entity\NotFoundLog;
use App\Entity\SuspiciousUrl;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotFoundLogControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string PROBE_URL = '/.env';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(5, $crawler->filter('.tabs.is-boxed li')->count());
    }

    public function testIncidentColumnHeaderIsPresent(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        $headers = $crawler->filter('table thead th');
        $headerTexts = $headers->each(static fn($node) => trim($node->text()));
        static::assertContains('Incident', $headerTexts);
    }

    public function testTopHundredUrlTableIsGone(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(0, $crawler->filter('.container .column.is-4')->count(), 'The top-100 sidebar column should be gone');
        static::assertSame(1, $crawler->filter('.container table')->count(), 'Only one table should remain');
    }

    public function testStatisticsButtonLinksToStatisticsPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(1, $crawler->filter('a[href="/en/admin/logs/404/statistics"]')->count());
    }

    public function testStatisticsPageRendersAggregates(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $this->loginAsAdmin($client);
        $this->seedNotFoundLog($em, self::PROBE_URL);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404/statistics');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(1, $crawler->filter('canvas[data-chart]')->count());
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('Top URLs', $content);
        static::assertStringContainsString(self::PROBE_URL, $content);
    }

    public function testMarkingAndUnmarkingUrlAsSuspicious(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $this->loginAsAdmin($client);
        $log = $this->seedNotFoundLog($em, self::PROBE_URL);
        $crawler = $client->request('GET', '/en/admin/logs/404');
        $toggleUrl = '/en/admin/logs/404/' . $log->getId() . '/suspicious';
        $link = $crawler->filter('a[href="' . $toggleUrl . '"]');
        static::assertSame(1, $link->count(), 'The row should carry a suspicious-toggle action');

        // Act
        $client->request('POST', $toggleUrl, ['_token' => (string) $link->attr('data-csrf-token')]);

        // Assert
        $this->assertResponseRedirects();
        $em->clear();
        static::assertNotNull($em->getRepository(SuspiciousUrl::class)->findOneBy(['url' => self::PROBE_URL]));

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404');
        $token = (string) $crawler->filter('a[href="' . $toggleUrl . '"]')->attr('data-csrf-token');
        $client->request('POST', $toggleUrl, ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        $em->clear();
        static::assertNull($em->getRepository(SuspiciousUrl::class)->findOneBy(['url' => self::PROBE_URL]));
    }

    public function testSuspiciousUrlIsSummarizedOnStatisticsPage(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $this->loginAsAdmin($client);
        $this->seedNotFoundLog($em, self::PROBE_URL);
        $this->seedNotFoundLog($em, self::PROBE_URL);
        $em->persist((new SuspiciousUrl())->setUrl(self::PROBE_URL)->setCreatedAt(new DateTimeImmutable()));
        $em->flush();

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/404/statistics');

        // Assert
        $this->assertResponseIsSuccessful();
        $suspiciousRow = $crawler->filter('a[href*="/suspicious/"]')->closest('tr');
        static::assertNotNull($suspiciousRow);
        static::assertStringContainsString(self::PROBE_URL, $suspiciousRow->text());
        static::assertStringContainsString('2', $suspiciousRow->text());
    }

    public function testGuestIsBlocked(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/logs/404');

        // Assert
        static::assertTrue($client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403);
    }

    public function testGuestIsBlockedFromStatistics(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/logs/404/statistics');

        // Assert
        static::assertTrue($client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403);
    }

    private function seedNotFoundLog(EntityManagerInterface $em, string $url): NotFoundLog
    {
        $log = new NotFoundLog();
        $log->setUrl($url);
        $log->setIp('203.0.113.5');
        $log->setUserAgent('curl/8.0');
        $log->setReferer('https://example.org/');
        $log->setCreatedAt(new DateTimeImmutable());
        $em->persist($log);
        $em->flush();

        return $log;
    }

    private function loginAsAdmin(KernelBrowser $client): EntityManagerInterface
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

        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
