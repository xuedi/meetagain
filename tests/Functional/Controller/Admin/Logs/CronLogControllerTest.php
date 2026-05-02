<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Logs;

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
        $rightText = $crawler->filter('.box .level .level-right')->text();
        static::assertStringContainsString(
            'Status: All',
            $rightText,
            'Default status dropdown trigger should read "Status: All"',
        );
        static::assertStringContainsString(
            'Range: 1 hour',
            $rightText,
            'Default range dropdown trigger should read "Range: 1 hour"',
        );
    }

    public function testCronListPageRendersTwoDropdownsWithExpectedOptions(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(
            2,
            $crawler->filter('.box .level .level-right .dropdown[data-admin-dropdown]')->count(),
            'Two dropdowns (status, range) should be rendered',
        );
        $items = $crawler->filter('.box .level .level-right .dropdown-item');
        $itemTexts = $items->each(static fn ($node) => trim($node->text()));
        foreach (['All', 'All problems', 'Warnings', 'Errors', 'Exceptions'] as $expected) {
            static::assertContains($expected, $itemTexts, sprintf('Status option "%s" should be present', $expected));
        }
        foreach (['1 hour', '6 hours', '24 hours', '1 week'] as $expected) {
            static::assertContains($expected, $itemTexts, sprintf('Range option "%s" should be present', $expected));
        }
    }

    public function testCronListPageReflectsActiveSelectionInDropdownTriggers(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/logs/cron?status=error&range=1w');

        // Assert
        $this->assertResponseIsSuccessful();
        $rightText = $crawler->filter('.box .level .level-right')->text();
        static::assertStringContainsString('Status: Errors', $rightText);
        static::assertStringContainsString('Range: 1 week', $rightText);
        static::assertSame(
            2,
            $crawler->filter('.box .level .level-right .dropdown-item.is-active')->count(),
            'Exactly one option in each dropdown should be marked active',
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
