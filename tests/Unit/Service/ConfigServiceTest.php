<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Config;
use App\Enum\ConfigType;
use App\Enum\ImageType;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ConfigServiceTest extends TestCase
{
    private Stub&ConfigRepository $configRepoStub;
    private Stub&EntityManagerInterface $entityManagerStub;
    private Stub&CacheInterface $cacheStub;
    private Stub&KernelInterface $kernelStub;
    private ConfigService $subject;

    protected function setUp(): void
    {
        $this->configRepoStub = $this->createStub(ConfigRepository::class);
        $this->entityManagerStub = $this->createStub(EntityManagerInterface::class);
        $this->cacheStub = $this->createStub(CacheInterface::class);
        $this->kernelStub = $this->createStub(KernelInterface::class);
        // Always simulate a cache miss so the callback (and thus the repo) is exercised
        $this->cacheStub
            ->method('get')
            ->willReturnCallback(fn(
                string $key,
                callable $callback,
            ): mixed => $callback($this->createStub(ItemInterface::class)));
        $this->subject = new ConfigService(
            repo: $this->configRepoStub,
            em: $this->entityManagerStub,
            cache: $this->cacheStub,
            kernel: $this->kernelStub,
            appState: $this->createStub(AppStateService::class),
        );
    }

    #[DataProvider('thumbnailSizeProvider')]
    public function testGetThumbnailSizes(ImageType $imageType, array $expected): void
    {
        static::assertSame($expected, $this->subject->getThumbnailSizes($imageType));
    }

    public static function thumbnailSizeProvider(): Generator
    {
        yield 'profile picture' => [
            ImageType::ProfilePicture,
            [[400, 400], [350, 350], [100, 100], [80, 80], [50, 50]],
        ];
        yield 'event teaser' => [
            ImageType::EventTeaser,
            [[1024, 768], [600, 400], [350, 263], [210, 140], [100, 100], [50, 50]],
        ];
        yield 'event upload' => [
            ImageType::EventUpload,
            [[1024, 768], [350, 263], [210, 140], [100, 100], [50, 50]],
        ];
        yield 'cms block' => [
            ImageType::CmsBlock,
            [[432, 432], [350, 350], [100, 100], [80, 80], [50, 50]],
        ];
        yield 'plugin dish' => [
            ImageType::PluginDish,
            [[1024, 768], [600, 400], [400, 400], [350, 263], [100, 100], [50, 50]],
        ];
        yield 'site logo' => [
            ImageType::SiteLogo,
            [[400, 400], [350, 350], [100, 100]],
        ];
    }

    public function testGetThumbnailSizeList(): void
    {
        $expected = [
            '1024x768' => 0,
            '600x400' => 0,
            '432x432' => 0,
            '400x500' => 0,
            '400x400' => 0,
            '350x438' => 0,
            '350x350' => 0,
            '350x263' => 0,
            '350x233' => 0,
            '300x200' => 0,
            '210x140' => 0,
            '200x250' => 0,
            '100x100' => 0,
            '80x80' => 0,
            '50x50' => 0,
        ];

        static::assertSame($expected, $this->subject->getThumbnailSizeList());
    }

    #[DataProvider('thumbnailSizeValidationProvider')]
    public function testIsValidThumbnailSize(ImageType $imageType, int $width, int $height, bool $expected): void
    {
        static::assertSame($expected, $this->subject->isValidThumbnailSize($imageType, $width, $height));
    }

    public static function thumbnailSizeValidationProvider(): Generator
    {
        yield 'profile picture 400x400 valid' => [ImageType::ProfilePicture, 400, 400, true];
        yield 'profile picture 80x80 valid' => [ImageType::ProfilePicture, 80, 80, true];
        yield 'profile picture 50x50 valid' => [ImageType::ProfilePicture, 50, 50, true];
        yield 'profile picture 1024x768 invalid' => [ImageType::ProfilePicture, 1024, 768, false];
        yield 'profile picture random size invalid' => [ImageType::ProfilePicture, 128000, 51200, false];
        yield 'event teaser 600x400 valid' => [ImageType::EventTeaser, 600, 400, true];
        yield 'event teaser 400x400 invalid' => [ImageType::EventTeaser, 400, 400, false];
        yield 'event upload 210x140 valid' => [ImageType::EventUpload, 210, 140, true];
        yield 'event upload 600x400 invalid' => [ImageType::EventUpload, 600, 400, false];
        yield 'cms block 432x432 valid' => [ImageType::CmsBlock, 432, 432, true];
        yield 'cms block 80x80 valid' => [ImageType::CmsBlock, 80, 80, true];
        yield 'cms block 400x400 invalid' => [ImageType::CmsBlock, 400, 400, false];
        yield 'plugin dish 600x400 valid' => [ImageType::PluginDish, 600, 400, true];
        yield 'plugin dish 100x100 valid' => [ImageType::PluginDish, 100, 100, true];
        yield 'plugin dish 50x50 valid' => [ImageType::PluginDish, 50, 50, true];
        yield 'plugin dish 80x80 invalid' => [ImageType::PluginDish, 80, 80, false];
    }

    public function testGetHostReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertSame('https://localhost', $this->subject->getHost());
    }

    public function testGetHostReturnsConfiguredValue(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('https://example.com'));

        static::assertSame('https://example.com', $this->subject->getHost());
    }

    public function testGetUrlReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertSame('localhost', $this->subject->getUrl());
    }

    public function testGetUrlReturnsConfiguredValue(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('example.com'));

        static::assertSame('example.com', $this->subject->getUrl());
    }

    public function testGetSystemUserIdReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertSame(1, $this->subject->getSystemUserId());
    }

    public function testGetSystemUserIdReturnsConfiguredValue(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('42'));

        static::assertSame(42, $this->subject->getSystemUserId());
    }

    public function testGetMailerAddressReturnsDefaultsWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $address = $this->subject->getMailerAddress();

        static::assertSame('sender@email.com', $address->getAddress());
        static::assertSame('email sender', $address->getName());
    }

    public function testGetMailerAddressReturnsConfiguredValues(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturnCallback(static fn(array $criteria) => match ($criteria['name']) {
                'email_sender_mail' => new Config()->setValue('noreply@example.com'),
                'email_sender_name' => new Config()->setValue('Example Sender'),
                default => null,
            });

        $address = $this->subject->getMailerAddress();

        static::assertSame('noreply@example.com', $address->getAddress());
        static::assertSame('Example Sender', $address->getName());
    }

    public function testIsShowFrontpageReturnsTrueByDefault(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertTrue($this->subject->isShowFrontpage());
    }

    public function testIsShowFrontpageReturnsTrueWhenEnabled(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('true'));

        static::assertTrue($this->subject->isShowFrontpage());
    }

    public function testIsShowFrontpageReturnsFalseWhenDisabled(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('false'));

        static::assertFalse($this->subject->isShowFrontpage());
    }

    public function testIsSendRsvpNotificationsReturnsTrueByDefault(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertTrue($this->subject->isSendRsvpNotifications());
    }

    public function testIsSendRsvpNotificationsReturnsTrueWhenEnabled(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('true'));

        static::assertTrue($this->subject->isSendRsvpNotifications());
    }

    public function testIsSendRsvpNotificationsReturnsFalseWhenDisabled(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('false'));

        static::assertFalse($this->subject->isSendRsvpNotifications());
    }

    public function testSaveFormCreatesNewSettings(): void
    {
        $configRepoStub = $this->createStub(ConfigRepository::class);
        $configRepoStub->method('findOneBy')->willReturn(null);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(6))->method('persist')->with(static::isInstanceOf(Config::class));
        $entityManagerMock->expects($this->exactly(6))->method('flush');

        $cacheStub = $this->createStub(CacheInterface::class);

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock, cache: $cacheStub, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        $subject->saveForm([
            'url' => 'example.com',
            'host' => 'https://example.com',
            'senderName' => 'Example Sender',
            'senderEmail' => 'noreply@example.com',
            'systemUser' => 42,
            'dateFormat' => 'Y-m-d H:i',
        ]);
    }

    public function testSaveFormUpdatesExistingSettings(): void
    {
        $existingConfig = new Config();
        $existingConfig->setName('test');
        $existingConfig->setValue('old_value');

        $configRepoStub = $this->createStub(ConfigRepository::class);
        $configRepoStub->method('findOneBy')->willReturn($existingConfig);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->exactly(6))->method('persist')->with(static::isInstanceOf(Config::class));
        $entityManagerMock->expects($this->exactly(6))->method('flush');

        $cacheStub = $this->createStub(CacheInterface::class);

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock, cache: $cacheStub, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        $subject->saveForm([
            'url' => 'new-example.com',
            'host' => 'https://new-example.com',
            'senderName' => 'New Sender',
            'senderEmail' => 'new@example.com',
            'systemUser' => 99,
            'dateFormat' => 'd.m.Y H:i',
        ]);
    }

    // ---- boolean getters: default, on, off ----

    #[DataProvider('booleanGetterProvider')]
    public function testBooleanGetters(string $method, ?string $configValue, bool $expected): void
    {
        // Arrange
        $returnValue = $configValue !== null ? new Config()->setValue($configValue) : null;
        $this->configRepoStub->method('findOneBy')->willReturn($returnValue);

        // Act & Assert
        static::assertSame($expected, $this->subject->$method());
    }

    public static function booleanGetterProvider(): iterable
    {
        yield 'isAutomaticRegistration default false'   => ['isAutomaticRegistration',   null,    false];
        yield 'isAutomaticRegistration enabled'         => ['isAutomaticRegistration',   'true',  true];
        yield 'isAutomaticRegistration disabled'        => ['isAutomaticRegistration',   'false', false];
        yield 'isSendAdminNotification default true'    => ['isSendAdminNotification',   null,    true];
        yield 'isSendAdminNotification disabled'        => ['isSendAdminNotification',   'false', false];
        yield 'isEmailDeliverySyncEnabled default false'=> ['isEmailDeliverySyncEnabled', null,   false];
        yield 'isEmailDeliverySyncEnabled enabled'      => ['isEmailDeliverySyncEnabled', 'true', true];
    }

    // ---- getSeoDescription ----

    #[DataProvider('seoDescriptionProvider')]
    public function testGetSeoDescription(string $context, string $configKey, string $storedValue, string $expected): void
    {
        // Arrange: only return a value when the correct config key is queried
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturnCallback(static fn(array $c) => $c['name'] === $configKey
                ? new Config()->setValue($storedValue)
                : null);

        // Act & Assert
        static::assertSame($expected, $this->subject->getSeoDescription($context));
    }

    public static function seoDescriptionProvider(): iterable
    {
        yield 'events context returns seo_description_events value'   => ['events',  'seo_description_events',  'All events', 'All events'];
        yield 'members context returns seo_description_members value' => ['members', 'seo_description_members', 'All members', 'All members'];
        yield 'default context returns seo_description_default value' => ['default', 'seo_description_default', 'Home', 'Home'];
        yield 'unknown context falls back to seo_description_default' => ['unknown', 'seo_description_default', 'Home', 'Home'];
        yield 'events context with no config returns empty string'    => ['events',  'nonexistent',             'anything', ''];
    }

    // ---- getDateFormat / getDateFormatFlatpickr ----

    public function testGetDateFormatReturnsDefaultWhenNotConfigured(): void
    {
        // Arrange
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        // Act & Assert
        static::assertSame('Y-m-d H:i', $this->subject->getDateFormat());
    }

    public function testGetDateFormatReturnsConfiguredValue(): void
    {
        // Arrange
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('d.m.Y H:i'));

        // Act & Assert
        static::assertSame('d.m.Y H:i', $this->subject->getDateFormat());
    }

    public function testGetDateFormatFlatpickrConvertsAmPmToken(): void
    {
        // Arrange: date format contains 'A' (AM/PM token)
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('Y-m-d h:i A'));

        // Act & Assert: 'A' must be replaced with 'K' for Flatpickr
        static::assertSame('Y-m-d h:i K', $this->subject->getDateFormatFlatpickr());
    }

    public function testGetDateFormatFlatpickrWithDefaultFormatIsUnchanged(): void
    {
        // Arrange: default format has no 'A'
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        // Act & Assert
        static::assertSame('Y-m-d H:i', $this->subject->getDateFormatFlatpickr());
    }

    // ---- getFooterColumnTitle ----

    public function testGetFooterColumnTitleReturnsConfiguredValue(): void
    {
        // Arrange — footer titles are stored in AppStateService, not the config repo
        $appStateStub = $this->createStub(AppStateService::class);
        $appStateStub->method('get')->willReturn('News');

        $subject = new ConfigService(
            repo: $this->configRepoStub,
            em: $this->entityManagerStub,
            cache: $this->cacheStub,
            kernel: $this->kernelStub,
            appState: $appStateStub,
        );

        // Act & Assert
        static::assertSame('News', $subject->getFooterColumnTitle('col1'));
    }

    public function testGetFooterColumnTitleReturnsEmptyStringWhenNotConfigured(): void
    {
        // Arrange — AppStateService returns null when not set
        $appStateStub = $this->createStub(AppStateService::class);
        $appStateStub->method('get')->willReturn(null);

        $subject = new ConfigService(
            repo: $this->configRepoStub,
            em: $this->entityManagerStub,
            cache: $this->cacheStub,
            kernel: $this->kernelStub,
            appState: $appStateStub,
        );

        // Act & Assert
        static::assertSame('', $subject->getFooterColumnTitle('col2'));
    }

    // ---- toggleBoolean ----

    public function testToggleBooleanFromTrueSetsFalseAndReturnsFalse(): void
    {
        // Arrange
        $config = new Config();
        $config->setName('some_feature');
        $config->setValue('true');

        $repoMock = $this->createMock(ConfigRepository::class);
        $repoMock->expects($this->once())->method('findOneBy')->with(['name' => 'some_feature'])->willReturn($config);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($config);
        $emMock->expects($this->once())->method('flush');

        $cacheMock = $this->createMock(CacheInterface::class);
        $cacheMock->expects($this->once())->method('delete')->with('config_some_feature');

        $subject = new ConfigService(repo: $repoMock, em: $emMock, cache: $cacheMock, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $result = $subject->toggleBoolean('some_feature');

        // Assert
        static::assertFalse($result);
        static::assertSame('false', $config->getValue());
    }

    public function testToggleBooleanFromFalseSetsAndReturnsTrueAndDeletesCache(): void
    {
        // Arrange
        $config = new Config();
        $config->setName('some_feature');
        $config->setValue('false');

        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn($config);

        $emMock = $this->createStub(EntityManagerInterface::class);

        $cacheMock = $this->createMock(CacheInterface::class);
        $cacheMock->expects($this->once())->method('delete')->with('config_some_feature');

        $subject = new ConfigService(repo: $repoStub, em: $emMock, cache: $cacheMock, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $result = $subject->toggleBoolean('some_feature');

        // Assert
        static::assertTrue($result);
        static::assertSame('true', $config->getValue());
    }

    public function testToggleBooleanThrowsWhenConfigNotFound(): void
    {
        // Arrange
        $repoMock = $this->createStub(ConfigRepository::class);
        $repoMock->method('findOneBy')->willReturn(null);

        $subject = new ConfigService(
            repo: $repoMock,
            em: $this->createStub(EntityManagerInterface::class),
            cache: $this->createStub(CacheInterface::class),
            kernel: $this->createStub(KernelInterface::class),
            appState: $this->createStub(AppStateService::class),
        );

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $subject->toggleBoolean('nonexistent');
    }

    // ---- saveSeoForm ----

    public function testSaveSeoFormPersistsThreeDescriptions(): void
    {
        // Arrange: no existing configs
        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->exactly(3))->method('persist')->with(static::isInstanceOf(Config::class));
        $emMock->expects($this->exactly(3))->method('flush');

        $subject = new ConfigService(
            repo: $repoStub,
            em: $emMock,
            cache: $this->createStub(CacheInterface::class),
            kernel: $this->createStub(KernelInterface::class),
            appState: $this->createStub(AppStateService::class),
        );

        // Act
        $subject->saveSeoForm([
            'seoDescriptionDefault' => 'Default',
            'seoDescriptionEvents'  => 'Events',
            'seoDescriptionMembers' => 'Members',
        ]);

        // Assert: verified by mock expectations above
    }

    // ---- getInt / setString / setInt ----

    public function testGetIntReturnsDefaultWhenNotConfigured(): void
    {
        // Arrange
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        // Act & Assert
        static::assertSame(99, $this->subject->getInt('missing_key', 99));
    }

    public function testGetIntReturnsConfiguredIntValue(): void
    {
        // Arrange
        $this->configRepoStub->method('findOneBy')->willReturn(new Config()->setValue('42'));

        // Act & Assert
        static::assertSame(42, $this->subject->getInt('some_key', 0));
    }

    public function testSetStringCreatesNewConfigEntityWhenNotExisting(): void
    {
        // Arrange
        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with(static::callback(
            static fn(Config $c) => $c->getName() === 'new_key'
                && $c->getValue() === 'hello'
                && $c->getType() === ConfigType::String,
        ));
        $emMock->expects($this->once())->method('flush');

        $cacheMock = $this->createMock(CacheInterface::class);
        $cacheMock->expects($this->once())->method('delete')->with('config_new_key');

        $subject = new ConfigService(repo: $repoStub, em: $emMock, cache: $cacheMock, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $subject->setString('new_key', 'hello');
    }

    public function testSetStringUpdatesExistingConfigEntity(): void
    {
        // Arrange
        $existing = new Config();
        $existing->setName('existing_key');
        $existing->setValue('old');

        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn($existing);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($existing);
        $emMock->expects($this->once())->method('flush');

        $subject = new ConfigService(repo: $repoStub, em: $emMock, cache: $this->createStub(CacheInterface::class), kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $subject->setString('existing_key', 'new_value');

        // Assert: the existing entity has the new value
        static::assertSame('new_value', $existing->getValue());
    }

    public function testSetIntCreatesNewConfigEntityWhenNotExisting(): void
    {
        // Arrange
        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with(static::callback(
            static fn(Config $c) => $c->getName() === 'count_key'
                && $c->getValue() === '7'
                && $c->getType() === ConfigType::Integer,
        ));
        $emMock->expects($this->once())->method('flush');

        $cacheMock = $this->createMock(CacheInterface::class);
        $cacheMock->expects($this->once())->method('delete')->with('config_count_key');

        $subject = new ConfigService(repo: $repoStub, em: $emMock, cache: $cacheMock, kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $subject->setInt('count_key', 7);
    }

    public function testSetIntUpdatesExistingConfigEntity(): void
    {
        // Arrange
        $existing = new Config();
        $existing->setName('count_key');
        $existing->setValue('3');

        $repoStub = $this->createStub(ConfigRepository::class);
        $repoStub->method('findOneBy')->willReturn($existing);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($existing);
        $emMock->expects($this->once())->method('flush');

        $subject = new ConfigService(repo: $repoStub, em: $emMock, cache: $this->createStub(CacheInterface::class), kernel: $this->createStub(KernelInterface::class), appState: $this->createStub(AppStateService::class));

        // Act
        $subject->setInt('count_key', 99);

        // Assert: value stored as string
        static::assertSame('99', $existing->getValue());
    }

    // ---- getBooleanConfigs ----

    public function testGetBooleanConfigsDelegatesToRepo(): void
    {
        // Arrange
        $repoMock = $this->createMock(ConfigRepository::class);
        $repoMock->expects($this->once())
            ->method('findBy')
            ->with(['type' => ConfigType::Boolean])
            ->willReturn([]);

        $subject = new ConfigService(
            repo: $repoMock,
            em: $this->createStub(EntityManagerInterface::class),
            cache: $this->createStub(CacheInterface::class),
            kernel: $this->createStub(KernelInterface::class),
            appState: $this->createStub(AppStateService::class),
        );

        // Act
        $result = $subject->getBooleanConfigs();

        // Assert
        static::assertSame([], $result);
    }
}
