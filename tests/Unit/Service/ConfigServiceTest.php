<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Config;
use App\Enum\ImageType;
use App\Repository\ConfigRepository;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
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
            [[400, 400], [100, 100], [80, 80], [50, 50]],
        ];
        yield 'event teaser' => [
            ImageType::EventTeaser,
            [[1024, 768], [600, 400], [210, 140], [100, 100], [50, 50]],
        ];
        yield 'event upload' => [
            ImageType::EventUpload,
            [[1024, 768], [210, 140], [100, 100], [50, 50]],
        ];
        yield 'cms block' => [
            ImageType::CmsBlock,
            [[432, 432], [100, 100], [80, 80], [50, 50]],
        ];
        yield 'plugin dish' => [
            ImageType::PluginDish,
            [[1024, 768], [600, 400], [400, 400], [100, 100], [50, 50]],
        ];
    }

    public function testGetThumbnailSizeList(): void
    {
        $expected = [
            '1024x768' => 0,
            '600x400' => 0,
            '432x432' => 0,
            '400x400' => 0,
            '300x200' => 0,
            '210x140' => 0,
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

    public function testIsShowFrontpageReturnsFalseByDefault(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        static::assertFalse($this->subject->isShowFrontpage());
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
        $entityManagerMock->expects($this->exactly(10))->method('persist')->with(static::isInstanceOf(Config::class));
        $entityManagerMock->expects($this->exactly(10))->method('flush');

        $cacheStub = $this->createStub(CacheInterface::class);

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock, cache: $cacheStub, kernel: $this->createStub(KernelInterface::class));

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
        $entityManagerMock->expects($this->exactly(10))->method('persist')->with(static::isInstanceOf(Config::class));
        $entityManagerMock->expects($this->exactly(10))->method('flush');

        $cacheStub = $this->createStub(CacheInterface::class);

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock, cache: $cacheStub, kernel: $this->createStub(KernelInterface::class));

        $subject->saveForm([
            'url' => 'new-example.com',
            'host' => 'https://new-example.com',
            'senderName' => 'New Sender',
            'senderEmail' => 'new@example.com',
            'systemUser' => 99,
            'dateFormat' => 'd.m.Y H:i',
        ]);
    }
}
