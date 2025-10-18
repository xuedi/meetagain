<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Config;
use App\Entity\ImageType;
use App\Repository\ConfigRepository;
use App\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigServiceTest extends TestCase
{
    private MockObject|ConfigRepository $configRepoMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private ConfigService $subject;

    protected function setUp(): void
    {
        $this->configRepoMock = $this->createMock(ConfigRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->subject = new ConfigService(
            repo: $this->configRepoMock,
            em: $this->entityManagerMock,
        );
    }

    #[DataProvider('getThumbnailSizeMatrix')]
    public function testThumbnailSizesFetching(ImageType $imageType, array $expected): void
    {
        $this->assertSame($expected, $this->subject->getThumbnailSizes($imageType));
    }

    public static function getThumbnailSizeMatrix(): Generator
    {
        yield 'test profile picture' => [
            ImageType::ProfilePicture,
            [
                [400, 400],
                [80, 80],
                [50, 50],
            ],
        ];
        yield 'test event teaser' => [
            ImageType::EventTeaser,
            [
                [1024, 768],
                [600, 400],
                [210, 140],
            ],
        ];
        yield 'test event upload' => [
            ImageType::EventUpload,
            [
                [1024, 768],
                [210, 140],
            ],
        ];
        yield 'test cms block' => [
            ImageType::CmsBlock,
            [
                [432, 432],
                [80, 80],
            ],
        ];
        yield 'test plugin dish preview' => [
            ImageType::PluginDishPreview,
            [
                [1024, 768],
                [400, 400],
                [100, 100],
                [50, 50],
            ],
        ];
        yield 'test plugin dish gallery' => [
            ImageType::PluginDishGallery,
            [
                [1024, 768],
                [400, 400],
            ],
        ];
    }

    public function testThumbnailSizeList(): void
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

    #[DataProvider('getThumbnailSizeCheckerMatrix')]
    public function testThumbnailSizeChecker(ImageType $imageType, int $width, int $height, bool $expected): void
    {
        $this->assertSame($expected, $this->subject->isValidThumbnailSize($imageType, $width, $height));
    }

    public static function getThumbnailSizeCheckerMatrix(): Generator
    {
        // Profile picture
        yield [ImageType::ProfilePicture, 1024, 768, false];
        yield [ImageType::ProfilePicture, 600, 400, false];
        yield [ImageType::ProfilePicture, 432, 432, false];
        yield [ImageType::ProfilePicture, 400, 400, true];
        yield [ImageType::ProfilePicture, 210, 140, false];
        yield [ImageType::ProfilePicture, 80, 80, true];
        yield [ImageType::ProfilePicture, 50, 50, true];
        yield [ImageType::ProfilePicture, 128000, 51200, false];

        // Event teaser
        yield [ImageType::EventTeaser, 600, 400, true];
        yield [ImageType::EventTeaser, 400, 400, false];

        // Event upload
        yield [ImageType::EventUpload, 210, 140, true];
        yield [ImageType::EventUpload, 600, 400, false];

        // CMS block
        yield [ImageType::CmsBlock, 432, 432, true];
        yield [ImageType::CmsBlock, 80, 80, true];
        yield [ImageType::CmsBlock, 400, 400, false];

        // Plugin dish preview
        yield [ImageType::PluginDishPreview, 100, 100, true];
        yield [ImageType::PluginDishPreview, 50, 50, true];
        yield [ImageType::PluginDishPreview, 80, 80, false];

        // Plugin dish gallery
        yield [ImageType::PluginDishGallery, 400, 400, true];
        yield [ImageType::PluginDishGallery, 50, 50, false];
    }

    public function testHostGetter(): void
    {
        $expected = 'https://localhost';
        $this->assertSame($expected, $this->subject->getHost());
    }

    public function testFrontPageToggleOn(): void
    {
        $this->configRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'show_frontpage'])
            ->willReturn(new Config()->setValue('true'));

        $this->assertTrue($this->subject->isShowFrontpage());
    }

    public function testFrontPageToggleOff(): void
    {
        $this->configRepoMock->method('findOneBy')->willReturn(new Config()->setValue('false'));

        $this->assertFalse($this->subject->isShowFrontpage());
    }

    public function testFrontPageToggleWithDefault(): void
    {
        $this->configRepoMock->method('findOneBy')->willReturn(null);

        $this->assertFalse($this->subject->isShowFrontpage());
    }

    public function testUrlGetter(): void
    {
        $this->assertSame('localhost', $this->subject->getUrl());
    }

    public function testSystemUserIdGetter(): void
    {
        $this->assertSame(1, $this->subject->getSystemUserId());
    }

    public function testMailerAddressDefaults(): void
    {
        $address = $this->subject->getMailerAddress();
        $this->assertSame('sender@email.com', $address->getAddress());
        $this->assertSame('email sender', $address->getName());
    }

    public function testSaveFormCreatesMissingSettings(): void
    {
        $this->configRepoMock->method('findOneBy')->willReturn(null);

        $formData = [
            'url' => 'example.com',
            'host' => 'https://example.com',
            'senderName' => 'Example Sender',
            'senderEmail' => 'noreply@example.com',
            'systemUser' => 42,
        ];

        $this->entityManagerMock
            ->expects($this->exactly(5))
            ->method('persist')
            ->with($this->isInstanceOf(Config::class));

        $this->entityManagerMock
            ->expects($this->exactly(5))
            ->method('flush');

        $this->subject->saveForm($formData);
    }
}
