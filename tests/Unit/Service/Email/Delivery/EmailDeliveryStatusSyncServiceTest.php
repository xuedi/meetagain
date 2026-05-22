<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery;

use App\Entity\EmailQueue;
use App\Enum\CronTaskStatus;
use App\Repository\EmailQueueRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\Delivery\EmailDeliveryLog;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use App\Service\Email\Delivery\EmailDeliveryStatusSyncService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

class EmailDeliveryStatusSyncServiceTest extends TestCase
{
    public function testIdentifierIsStable(): void
    {
        $service = $this->makeService();

        static::assertSame('email-delivery-sync', $service->getIdentifier());
    }

    public function testSyncPendingReturnsUnavailableWhenProviderIsUnavailable(): void
    {
        // Arrange
        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn(false);

        $repo = $this->createMock(EmailQueueRepository::class);
        $repo->expects($this->never())->method('findWithProviderMessageIdAndNoStatus');

        $service = $this->makeService(provider: $provider, repo: $repo);

        // Act
        $result = $service->syncPending();

        // Assert
        static::assertFalse($result->available);
        static::assertSame(0, $result->updated);
        static::assertSame(0, $result->checked);
    }

    public function testSyncPendingUpdatesStatusForLogsThatExist(): void
    {
        // Arrange - 2 queue entries, both have provider logs
        $email1 = $this->makeQueueEntry(1, 'tx-1');
        $email2 = $this->makeQueueEntry(2, 'tx-2');

        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn(true);
        $provider
            ->method('getLogByMessageId')
            ->willReturnCallback(static fn(string $id) => match ($id) {
                'tx-1' => self::makeLog('delivered'),
                'tx-2' => self::makeLog('bounced'),
                default => null,
            });

        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('findWithProviderMessageIdAndNoStatus')->willReturn([$email1, $email2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->makeService(provider: $provider, repo: $repo, em: $em);

        // Act
        $result = $service->syncPending();

        // Assert
        static::assertTrue($result->available);
        static::assertSame(2, $result->updated);
        static::assertSame(2, $result->checked);
        static::assertSame('delivered', $email1->getProviderStatus());
        static::assertSame('bounced', $email2->getProviderStatus());
    }

    public function testSyncPendingSkipsEntriesWithNoMatchingLog(): void
    {
        // Arrange - 2 entries, only one has a log
        $email1 = $this->makeQueueEntry(1, 'tx-1');
        $email2 = $this->makeQueueEntry(2, 'missing');

        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn(true);
        $provider->method('getLogByMessageId')->willReturnCallback(static fn(string $id) => $id === 'tx-1'
            ? self::makeLog('delivered')
            : null);

        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('findWithProviderMessageIdAndNoStatus')->willReturn([$email1, $email2]);

        $service = $this->makeService(provider: $provider, repo: $repo);

        // Act
        $result = $service->syncPending();

        // Assert
        static::assertSame(1, $result->updated);
        static::assertSame(2, $result->checked);
        static::assertSame('delivered', $email1->getProviderStatus());
        static::assertNull($email2->getProviderStatus());
    }

    public function testRunCronTaskReturnsOkDisabledWhenSyncSwitchedOff(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isEmailDeliverySyncEnabled')->willReturn(false);

        $provider = $this->createMock(EmailDeliveryProviderInterface::class);
        $provider->expects($this->never())->method('isAvailable');

        $service = $this->makeService(provider: $provider, configService: $config);

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('disabled', $result->message);
    }

    public function testRunCronTaskReportsProviderUnavailable(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isEmailDeliverySyncEnabled')->willReturn(true);

        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn(false);

        $service = $this->makeService(provider: $provider, configService: $config);

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('provider unavailable', $result->message);
    }

    public function testRunCronTaskWritesProgressMessageOnSuccess(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isEmailDeliverySyncEnabled')->willReturn(true);

        $email = $this->makeQueueEntry(1, 'tx-1');
        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn(true);
        $provider->method('getLogByMessageId')->willReturn(self::makeLog('delivered'));

        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('findWithProviderMessageIdAndNoStatus')->willReturn([$email]);

        $service = $this->makeService(provider: $provider, repo: $repo, configService: $config);
        $output = new BufferedOutput();

        // Act
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('1/1 synced', $result->message);
        static::assertStringContainsString('1/1 synced', $output->fetch());
    }

    public function testRunCronTaskCatchesExceptions(): void
    {
        // Arrange - config throws
        $config = $this->createStub(ConfigService::class);
        $config->method('isEmailDeliverySyncEnabled')->willThrowException(new RuntimeException('config blew up'));

        $service = $this->makeService(configService: $config);
        $output = new BufferedOutput();

        // Act
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::exception, $result->status);
        static::assertSame('config blew up', $result->message);
        static::assertStringContainsString('config blew up', $output->fetch());
    }

    private function makeService(
        ?EmailDeliveryProviderInterface $provider = null,
        ?EmailQueueRepository $repo = null,
        ?EntityManagerInterface $em = null,
        ?ConfigService $configService = null,
    ): EmailDeliveryStatusSyncService {
        return new EmailDeliveryStatusSyncService(
            $provider ?? $this->createStub(EmailDeliveryProviderInterface::class),
            $repo ?? $this->createStub(EmailQueueRepository::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            $configService ?? $this->createStub(ConfigService::class),
        );
    }

    private function makeQueueEntry(int $id, string $providerMessageId): EmailQueue
    {
        $email = new EmailQueue();
        $email->setProviderMessageId($providerMessageId);
        $ref = new \ReflectionClass($email);
        $prop = $ref->getProperty('id');
        $prop->setValue($email, $id);
        return $email;
    }

    private static function makeLog(string $status): EmailDeliveryLog
    {
        return new EmailDeliveryLog(
            messageId: 'x',
            status: $status,
            recipientEmail: 'x@y.z',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            bounceType: null,
            mailboxProvider: null,
        );
    }
}
