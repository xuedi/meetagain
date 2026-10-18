<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Internal\Message\RunTask;
use Module\Task\Internal\Queue;
use Module\Task\Tests\Stub\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Tests\Module\Members;

final class AdminPagesTest extends WebTestCase
{
    private const string BASE = '/en/admin/logs/tasks';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    #[DataProvider('providePages')]
    public function testAGuestIsSentAwayFromEveryPage(string $path): void
    {
        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseRedirects();
    }

    #[DataProvider('providePages')]
    public function testAMemberWhoIsNoAdminIsRefusedEveryPage(string $path): void
    {
        // Arrange
        $this->client->loginUser($this->members()->member('Member'));

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('providePages')]
    public function testEveryPageRendersForAnAdmin(string $path): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $this->client->request('GET', self::BASE . $path);

        // Assert
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePages(): iterable
    {
        yield 'schedules' => [''];
        yield 'runs' => ['/runs'];
        yield 'queue' => ['/queue'];
    }

    public function testTheOldCronLogAddressRedirectsAnAdminToTheTasksPage(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $this->client->request('GET', '/en/admin/logs/cron');

        // Assert
        self::assertResponseRedirects(self::BASE, 301);
    }

    public function testTheSchedulesListEveryRegisteredTask(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $crawler = $this->client->request('GET', self::BASE);

        // Assert
        self::assertStringContainsString(Task::IDENTIFIER, $crawler->filter('table')->text());
    }

    public function testRunNowHandsTheTaskToAWorker(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $token = $this->client
            ->request('GET', self::BASE)
            ->filter('a[href$="/' . Task::IDENTIFIER . '/run"]')
            ->attr('data-csrf-token');

        // Act
        $this->client->request('POST', self::BASE . '/' . Task::IDENTIFIER . '/run', ['_token' => $token]);

        // Assert
        self::assertResponseRedirects(self::BASE);
        $sent = array_map(static fn(Envelope $envelope): object => $envelope->getMessage(), $this->async()->getSent());
        self::assertEquals([new RunTask(Task::IDENTIFIER)], $sent);
    }

    public function testRunNowWithoutAValidTokenIsRejected(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));

        // Act
        $this->client->request('POST', self::BASE . '/' . Task::IDENTIFIER . '/run', ['_token' => 'forged']);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $this->async()->getSent());
    }

    public function testAFailedMessageIsListedAndRetriedOnItsOriginalTransport(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $this->sendToFailureTransport(new RunTask(Task::IDENTIFIER));
        $crawler = $this->client->request('GET', self::BASE . '/queue');
        $retry = $crawler->filter('a[href*="/failed/"][href$="/retry"]');

        // Act
        $this->client->request('POST', (string) $retry->attr('href'), ['_token' => $retry->attr('data-csrf-token')]);

        // Assert
        self::assertStringContainsString('worker went away', $crawler->filter('table')->eq(1)->text());
        self::assertResponseRedirects(self::BASE . '/queue');
        self::assertSame(0, $this->queue()->failedCount());
        $sent = array_map(static fn(Envelope $envelope): object => $envelope->getMessage(), $this->async()->getSent());
        self::assertEquals([new RunTask(Task::IDENTIFIER)], $sent);
    }

    public function testADeletedFailedMessageIsGoneWithoutARetry(): void
    {
        // Arrange
        $this->client->loginUser($this->members()->admin('Admin'));
        $this->sendToFailureTransport(new RunTask(Task::IDENTIFIER));
        $delete = $this->client->request('GET', self::BASE . '/queue')->filter('a[href*="/failed/"][href$="/delete"]');

        // Act
        $this->client->request('POST', (string) $delete->attr('href'), ['_token' => $delete->attr('data-csrf-token')]);

        // Assert
        self::assertResponseRedirects(self::BASE . '/queue');
        self::assertSame(0, $this->queue()->failedCount());
        self::assertSame([], $this->async()->getSent());
    }

    private function sendToFailureTransport(object $message): void
    {
        /** @var TransportInterface $failed */
        $failed = self::getContainer()->get('messenger.transport.failed');
        $failed->send(new Envelope($message, [
            new SentToFailureTransportStamp('async'),
            ErrorDetailsStamp::create(new RuntimeException('worker went away')),
        ]));
    }

    private function async(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function queue(): Queue
    {
        return self::getContainer()->get(Queue::class);
    }

    private function members(): Members
    {
        return new Members(self::getContainer()->get(EntityManagerInterface::class));
    }
}
