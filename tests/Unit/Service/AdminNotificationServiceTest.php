<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Emails\Types\AdminNotificationEmail;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Admin\AdminNotificationService;
use App\Service\Config\ConfigService;
use App\Service\Notification\Admin\AdminNotificationItem;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AdminNotificationServiceTest extends TestCase
{
    private function buildService(
        array $providers,
        AdminNotificationEmail $adminNotificationEmail,
        UserRepository $userRepository,
        TagAwareCacheInterface $cache,
        ConfigService $configService,
    ): AdminNotificationService {
        return new AdminNotificationService(
            providers: $providers,
            adminNotificationEmail: $adminNotificationEmail,
            userRepository: $userRepository,
            appCache: $cache,
            configService: $configService,
            logger: $this->createStub(LoggerInterface::class),
            clock: new MockClock(new DateTimeImmutable('2026-01-01 10:00:00')),
        );
    }

    public function testReturnsDisabledWhenConfigOff(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(false);

        $service = $this->buildService(
            providers: [],
            adminNotificationEmail: $this->createStub(AdminNotificationEmail::class),
            userRepository: $this->createStub(UserRepository::class),
            cache: $this->createStub(TagAwareCacheInterface::class),
            configService: $config,
        );

        // Act
        $result = $service->processNotification();

        // Assert
        static::assertSame('disabled', $result);
    }

    public function testReturnsNothingPendingWhenNoProviders(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(true);

        $service = $this->buildService(
            providers: [],
            adminNotificationEmail: $this->createStub(AdminNotificationEmail::class),
            userRepository: $this->createStub(UserRepository::class),
            cache: $this->createStub(TagAwareCacheInterface::class),
            configService: $config,
        );

        // Act
        $result = $service->processNotification();

        // Assert
        static::assertSame('nothing pending', $result);
    }

    public function testReturnsNoNewItemsWhenNothingNewerThanLastSent(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(true);

        $pendingAt = new DateTimeImmutable('2025-12-01 08:00:00');
        $lastSentAt = new DateTimeImmutable('2025-12-02 08:00:00'); // newer than pending

        $provider = $this->createStub(AdminNotificationProviderInterface::class);
        $provider->method('getLatestPendingAt')->willReturn($pendingAt);

        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('get')->willReturn($lastSentAt->format(DateTimeImmutable::ATOM));

        $service = $this->buildService(
            providers: [$provider],
            adminNotificationEmail: $this->createStub(AdminNotificationEmail::class),
            userRepository: $this->createStub(UserRepository::class),
            cache: $cache,
            configService: $config,
        );

        // Act
        $result = $service->processNotification();

        // Assert
        static::assertSame('no new items', $result);
    }

    public function testSendsEmailToAllAdminsWhenNewItemsExist(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(true);

        $pendingAt = new DateTimeImmutable('2026-01-01 09:00:00');

        $provider = $this->createStub(AdminNotificationProviderInterface::class);
        $provider->method('getLatestPendingAt')->willReturn($pendingAt);
        $provider->method('getSection')->willReturn('Users Pending Approval');
        $provider
            ->method('getPendingItems')
            ->willReturn([
                new AdminNotificationItem('Jane Smith (jane@example.org)', 'app_admin_member'),
            ]);

        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('get')->willReturn(null); // no previous send

        $adminUser = $this->createStub(User::class);
        $adminUser->method('getName')->willReturn('Admin');
        $adminUser->method('getEmail')->willReturn('admin@example.org');
        $adminUser->method('getLocale')->willReturn('en');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAdminUsers')->willReturn([$adminUser]);

        $emailMock = $this->createMock(AdminNotificationEmail::class);
        $emailMock
            ->expects($this->once())
            ->method('send')
            ->with(static::callback(
                static fn($ctx) => $ctx['user'] === $adminUser && str_contains($ctx['sectionsHtml'], 'Jane Smith'),
            ));

        $service = $this->buildService(
            providers: [$provider],
            adminNotificationEmail: $emailMock,
            userRepository: $userRepository,
            cache: $cache,
            configService: $config,
        );

        // Act
        $result = $service->processNotification();

        // Assert
        static::assertSame('1 sent', $result);
    }

    public function testSkipsProvidersWithNoItems(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(true);

        $pendingAt = new DateTimeImmutable('2026-01-01 09:00:00');

        $provider = $this->createStub(AdminNotificationProviderInterface::class);
        $provider->method('getLatestPendingAt')->willReturn($pendingAt);
        $provider->method('getPendingItems')->willReturn([]); // empty section

        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('get')->willReturn(null);

        $service = $this->buildService(
            providers: [$provider],
            adminNotificationEmail: $this->createStub(AdminNotificationEmail::class),
            userRepository: $this->createStub(UserRepository::class),
            cache: $cache,
            configService: $config,
        );

        // Act
        $result = $service->processNotification();

        // Assert
        static::assertSame('no items', $result);
    }
}
