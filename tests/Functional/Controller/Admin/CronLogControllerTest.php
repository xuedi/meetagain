<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\CronLog;
use App\Enum\CronTaskStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CronLogControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testCronListPageRendersNewTabStripAndTopComponent(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(
            1,
            $crawler->filter('.tabs.is-boxed li.is-active')->count(),
            'Exactly one tab should be active',
        );
        static::assertStringContainsString(
            '/admin/logs/cron',
            (string) $crawler->filter('.tabs.is-boxed li.is-active a')->attr('href'),
            'Active tab should link to cron route',
        );
        static::assertSame(
            4,
            $crawler->filter('.tabs.is-boxed li')->count(),
            'Four logs tabs should be rendered',
        );
        static::assertGreaterThan(
            0,
            $crawler->filter('.box .level .level-left strong')->count(),
            'Top box should contain a <strong> info item',
        );
        static::assertStringContainsString(
            'Show problems only',
            $crawler->filter('.box .level .level-right')->text(),
            'Default action should be "Show problems only"',
        );
    }

    public function testCronListPageWithProblemsOnlyTogglesButtonLabel(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron?problemsOnly=1');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString(
            'Show all',
            $crawler->filter('.box .level .level-right')->text(),
            'Toggled action should be "Show all"',
        );
    }

    public function testCronListPageDefaultFiltersToLast24HoursAndShowsToggleButton(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString(
            'range: 24 hours',
            $crawler->filter('.box .level .level-left')->text(),
            'Default page should display the 24 hours range info text',
        );
        $rightLinks = $crawler->filter('.box .level .level-right a');
        $hrefs = $rightLinks->each(static fn ($node) => (string) $node->attr('href'));
        $hasShowAllLink = false;
        foreach ($hrefs as $href) {
            if (str_contains($href, 'showAll=1')) {
                $hasShowAllLink = true;
                break;
            }
        }
        static::assertTrue($hasShowAllLink, 'A toggle action should link to ?showAll=1');
    }

    public function testCronListPageWithShowAllSwapsRangeInfoAndOffersReverseToggle(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron?showAll=1');

        // Assert
        $this->assertResponseIsSuccessful();
        $leftText = $crawler->filter('.box .level .level-left')->text();
        static::assertStringContainsString(
            'range: all',
            $leftText,
            'Showing all entries should display the "range: all" info text',
        );
        static::assertStringNotContainsString(
            'range: 24 hours',
            $leftText,
            '"range: 24 hours" should not appear when showing all entries',
        );
        static::assertStringContainsString(
            'Last 24 hours',
            $crawler->filter('.box .level .level-right')->text(),
            'Reverse toggle button should appear when showing all entries',
        );
    }

    public function testCronDetailPageRendersNewTabStripAndTopComponent(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $log = new CronLog(
            runAt: new DateTimeImmutable('2026-04-30 12:00:00'),
            status: CronTaskStatus::ok,
            durationMs: 1234,
            tasks: [
                ['identifier' => 'task.x', 'status' => 'ok', 'message' => '', 'duration_ms' => 1200],
            ],
        );
        $em->persist($log);
        $em->flush();

        try {
            // Act
            $crawler = $client->request('GET', '/en/admin/logs/cron/' . $log->getId());

            // Assert
            $this->assertResponseIsSuccessful();
            static::assertSame(
                4,
                $crawler->filter('.tabs.is-boxed li')->count(),
                'Tab strip should match list page (4 tabs)',
            );
            static::assertSame(
                1,
                $crawler->filter('.tabs.is-boxed li.is-active')->count(),
                'Cron tab should be active',
            );
            $strong = $crawler->filter('.box .level .level-left strong');
            static::assertGreaterThan(0, $strong->count(), 'Timestamp should appear in <strong>');
            static::assertStringContainsString(
                '2026-04-30 12:00:00',
                $strong->text(),
                'Formatted timestamp should appear in info item',
            );
            static::assertStringContainsString(
                'ms total',
                $crawler->filter('.box .level .level-left')->text(),
                'Duration suffix should appear in info area',
            );
            $back = $crawler->filter('.box .level .level-right a');
            static::assertGreaterThan(0, $back->count(), 'Back action button should be present');
            static::assertStringContainsString(
                '/admin/logs/cron',
                (string) $back->attr('href'),
                'Back button should target the list',
            );
        } finally {
            $em->clear();
            $reloaded = $em->getRepository(CronLog::class)->find($log->getId());
            if ($reloaded !== null) {
                $em->remove($reloaded);
                $em->flush();
            }
        }
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
