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
            em: $this->entityManagerMock
        );
    }

    #[DataProvider('getThumbnailSizeMatrix')]
    public function testThumbnailSizesFetching(ImageType $imageType, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->subject->getThumbnailSizes($imageType)
        );
    }

    public static function getThumbnailSizeMatrix(): Generator
    {
        yield 'test profile picture' => [
            ImageType::ProfilePicture, [
                [400, 400],
                [80, 80],
                [50, 50],
            ],
        ];
        yield 'test event teaser' => [
            ImageType::EventTeaser, [
                [1024, 768],
                [600, 400],
                [210, 140],
            ],
        ];
        yield 'test event upload' => [
            ImageType::EventUpload, [
                [1024, 768],
                [210, 140],
            ],
        ];
        yield 'test cms block' => [
            ImageType::CmsBlock, [
                [432, 432],
                [80, 80],
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
        $this->assertSame(
            $expected,
            $this->subject->getThumbnailSizeList()
        );
    }

    #[DataProvider('getThumbnailSizeCheckerMatrix')]
    public function testThumbnailSizeChecker(ImageType $imageType, int $width, int $height, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->subject->isValidThumbnailSize($imageType, $width, $height)
        );
    }

    public static function getThumbnailSizeCheckerMatrix(): Generator
    {
        yield [ImageType::ProfilePicture, 1024, 768, false];
        yield [ImageType::ProfilePicture, 600, 400, false];
        yield [ImageType::ProfilePicture, 432, 432, false];
        yield [ImageType::ProfilePicture, 400, 400, true];
        yield [ImageType::ProfilePicture, 210, 140, false];
        yield [ImageType::ProfilePicture, 80, 80, true];
        yield [ImageType::ProfilePicture, 50, 50, true];
        yield [ImageType::ProfilePicture, 128000, 51200, false];
    }

    public function testHostGetter(): void
    {
        $expected = 'https://localhost';
        $this->assertSame(
            $expected,
            $this->subject->getHost()
        );
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
        $this->configRepoMock
            ->method('findOneBy')
            ->willReturn(new Config()->setValue('false'));

        $this->assertFalse($this->subject->isShowFrontpage());
    }

    public function testFrontPageToggleWithDefault(): void
    {
        $this->configRepoMock
            ->method('findOneBy')
            ->willReturn(null);

        $this->assertFalse($this->subject->isShowFrontpage());
    }
}
