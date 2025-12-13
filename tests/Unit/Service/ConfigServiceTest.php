<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Config;
use App\Entity\ImageType;
use App\Repository\ConfigRepository;
use App\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConfigServiceTest extends TestCase
{
    private Stub&ConfigRepository $configRepoStub;
    private Stub&EntityManagerInterface $entityManagerStub;
    private ConfigService $subject;

    protected function setUp(): void
    {
        $this->configRepoStub = $this->createStub(ConfigRepository::class);
        $this->entityManagerStub = $this->createStub(EntityManagerInterface::class);
        $this->subject = new ConfigService(
            repo: $this->configRepoStub,
            em: $this->entityManagerStub,
        );
    }

    #[DataProvider('thumbnailSizeProvider')]
    public function testGetThumbnailSizes(ImageType $imageType, array $expected): void
    {
        $this->assertSame($expected, $this->subject->getThumbnailSizes($imageType));
    }

    public static function thumbnailSizeProvider(): Generator
    {
        yield 'profile picture' => [
            ImageType::ProfilePicture,
            [[400, 400], [80, 80], [50, 50]],
        ];
        yield 'event teaser' => [
            ImageType::EventTeaser,
            [[1024, 768], [600, 400], [210, 140]],
        ];
        yield 'event upload' => [
            ImageType::EventUpload,
            [[1024, 768], [210, 140]],
        ];
        yield 'cms block' => [
            ImageType::CmsBlock,
            [[432, 432], [80, 80]],
        ];
        yield 'plugin dish preview' => [
            ImageType::PluginDishPreview,
            [[1024, 768], [400, 400], [100, 100], [50, 50]],
        ];
        yield 'plugin dish gallery' => [
            ImageType::PluginDishGallery,
            [[1024, 768], [400, 400]],
        ];
    }

    public function testGetThumbnailSizeList(): void
    {
        $expected = [
            '1024x768' => 0,
            '600x400' => 0,
            '432x432' => 0,
            '400x400' => 0,
            '210x140' => 0,
            '80x80' => 0,
            '50x50' => 0,
        ];

        $this->assertSame($expected, $this->subject->getThumbnailSizeList());
    }

    #[DataProvider('thumbnailSizeValidationProvider')]
    public function testIsValidThumbnailSize(ImageType $imageType, int $width, int $height, bool $expected): void
    {
        $this->assertSame($expected, $this->subject->isValidThumbnailSize($imageType, $width, $height));
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
        yield 'plugin dish preview 100x100 valid' => [ImageType::PluginDishPreview, 100, 100, true];
        yield 'plugin dish preview 50x50 valid' => [ImageType::PluginDishPreview, 50, 50, true];
        yield 'plugin dish preview 80x80 invalid' => [ImageType::PluginDishPreview, 80, 80, false];
        yield 'plugin dish gallery 400x400 valid' => [ImageType::PluginDishGallery, 400, 400, true];
        yield 'plugin dish gallery 50x50 invalid' => [ImageType::PluginDishGallery, 50, 50, false];
    }

    public function testGetHostReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $this->assertSame('https://localhost', $this->subject->getHost());
    }

    public function testGetHostReturnsConfiguredValue(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturn((new Config())->setValue('https://example.com'));

        $this->assertSame('https://example.com', $this->subject->getHost());
    }

    public function testGetUrlReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $this->assertSame('localhost', $this->subject->getUrl());
    }

    public function testGetUrlReturnsConfiguredValue(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturn((new Config())->setValue('example.com'));

        $this->assertSame('example.com', $this->subject->getUrl());
    }

    public function testGetSystemUserIdReturnsDefaultWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $this->assertSame(1, $this->subject->getSystemUserId());
    }

    public function testGetSystemUserIdReturnsConfiguredValue(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturn((new Config())->setValue('42'));

        $this->assertSame(42, $this->subject->getSystemUserId());
    }

    public function testGetMailerAddressReturnsDefaultsWhenNotConfigured(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $address = $this->subject->getMailerAddress();

        $this->assertSame('sender@email.com', $address->getAddress());
        $this->assertSame('email sender', $address->getName());
    }

    public function testGetMailerAddressReturnsConfiguredValues(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturnCallback(fn(array $criteria) => match ($criteria['name']) {
                'email_sender_mail' => (new Config())->setValue('noreply@example.com'),
                'email_sender_name' => (new Config())->setValue('Example Sender'),
                default => null,
            });

        $address = $this->subject->getMailerAddress();

        $this->assertSame('noreply@example.com', $address->getAddress());
        $this->assertSame('Example Sender', $address->getName());
    }

    public function testIsShowFrontpageReturnsFalseByDefault(): void
    {
        $this->configRepoStub->method('findOneBy')->willReturn(null);

        $this->assertFalse($this->subject->isShowFrontpage());
    }

    public function testIsShowFrontpageReturnsTrueWhenEnabled(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturn((new Config())->setValue('true'));

        $this->assertTrue($this->subject->isShowFrontpage());
    }

    public function testIsShowFrontpageReturnsFalseWhenDisabled(): void
    {
        $this->configRepoStub
            ->method('findOneBy')
            ->willReturn((new Config())->setValue('false'));

        $this->assertFalse($this->subject->isShowFrontpage());
    }

    public function testSaveFormCreatesNewSettings(): void
    {
        $configRepoStub = $this->createStub(ConfigRepository::class);
        $configRepoStub->method('findOneBy')->willReturn(null);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->exactly(5))
            ->method('persist')
            ->with($this->isInstanceOf(Config::class));
        $entityManagerMock
            ->expects($this->exactly(5))
            ->method('flush');

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock);

        $subject->saveForm([
            'url' => 'example.com',
            'host' => 'https://example.com',
            'senderName' => 'Example Sender',
            'senderEmail' => 'noreply@example.com',
            'systemUser' => 42,
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
        $entityManagerMock
            ->expects($this->exactly(5))
            ->method('persist')
            ->with($this->isInstanceOf(Config::class));
        $entityManagerMock
            ->expects($this->exactly(5))
            ->method('flush');

        $subject = new ConfigService(repo: $configRepoStub, em: $entityManagerMock);

        $subject->saveForm([
            'url' => 'new-example.com',
            'host' => 'https://new-example.com',
            'senderName' => 'New Sender',
            'senderEmail' => 'new@example.com',
            'systemUser' => 99,
        ]);
    }
}