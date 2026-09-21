<?php declare(strict_types=1);

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
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
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
            translator: $this->translator(),
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
            ->with(static::callback(static fn($ctx) => $ctx['user'] === $adminUser && str_contains($ctx['sectionsHtml'], 'Jane Smith')));

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

    public function testEachRecipientGetsTheSummaryInTheirOwnLanguage(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isSendAdminNotification')->willReturn(true);

        $provider = $this->createStub(AdminNotificationProviderInterface::class);
        $provider->method('getLatestPendingAt')->willReturn(new DateTimeImmutable('2026-01-01 09:00:00'));
        $provider->method('getSection')->willReturn(new TranslatableMessage('notifications.section_reported_images'));
        $provider
            ->method('getPendingItems')
            ->willReturn([
                new AdminNotificationItem(new TranslatableMessage('notifications.item_reported_image', [
                    '%image%' => '7',
                    '%reason%' => new TranslatableMessage('report.reason_privacy'),
                ])),
            ]);

        $cache = $this->createStub(TagAwareCacheInterface::class);
        $cache->method('get')->willReturn(null);

        $german = $this->createStub(User::class);
        $german->method('getLocale')->willReturn('de');
        $english = $this->createStub(User::class);
        $english->method('getLocale')->willReturn('en');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAdminUsers')->willReturn([$german, $english]);

        $sent = [];
        $email = $this->createStub(AdminNotificationEmail::class);
        $email
            ->method('send')
            ->willReturnCallback(static function (array $context) use (&$sent): void {
                $sent[$context['user']->getLocale()] = $context['sectionsHtml'];
            });

        $service = $this->buildService(
            providers: [$provider],
            adminNotificationEmail: $email,
            userRepository: $userRepository,
            cache: $cache,
            configService: $config,
        );

        // Act
        $service->processNotification();

        // Assert
        static::assertSame('<h3>Gemeldete Bilder</h3><ul><li>Bild #7 gemeldet: Privatsphäre</li></ul>', $sent['de']);
        static::assertSame('<h3>Reported images</h3><ul><li>Image #7 reported: Privacy</li></ul>', $sent['en']);
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

    private function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource(
            'array',
            [
                'notifications.section_reported_images' => 'Reported images',
                'notifications.item_reported_image' => 'Image #%image% reported: %reason%',
                'report.reason_privacy' => 'Privacy',
            ],
            'en',
        );
        $translator->addResource(
            'array',
            [
                'notifications.section_reported_images' => 'Gemeldete Bilder',
                'notifications.item_reported_image' => 'Bild #%image% gemeldet: %reason%',
                'report.reason_privacy' => 'Privatsphäre',
            ],
            'de',
        );

        return $translator;
    }
}
