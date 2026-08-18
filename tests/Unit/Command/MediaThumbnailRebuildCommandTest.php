<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\MediaThumbnailRebuildCommand;
use App\Enum\ImageType;
use App\Service\Media\ImageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class MediaThumbnailRebuildCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        static::assertSame('app:media:thumbnail-rebuild', $this->command()->getName());
    }

    public function testWithoutTypeOptionEveryTypeIsCovered(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('regenerateAllThumbnails')->with(null)->willReturn(12);
        $tester = new CommandTester($this->command($imageService));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Created 12 missing thumbnail(s) across all image types', $tester->getDisplay());
    }

    public function testTypeOptionIsResolvedByEnumCaseName(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('regenerateAllThumbnails')->with(ImageType::PluginBooksCover)->willReturn(3);
        $tester = new CommandTester($this->command($imageService));

        // Act
        $exitCode = $tester->execute(['--type' => 'PluginBooksCover']);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Created 3 missing thumbnail(s) for image type PluginBooksCover', $tester->getDisplay());
    }

    public function testTypeOptionIsCaseInsensitive(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('regenerateAllThumbnails')->with(ImageType::PluginFilmsPoster)->willReturn(0);
        $tester = new CommandTester($this->command($imageService));

        // Act
        $exitCode = $tester->execute(['--type' => 'pluginfilmsposter']);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testUnknownTypeFailsAndListsTheValidNames(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('regenerateAllThumbnails');
        $tester = new CommandTester($this->command($imageService));

        // Act
        $exitCode = $tester->execute(['--type' => 'books-cover']);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Unknown image type "books-cover"', $tester->getDisplay());
        static::assertStringContainsString('PluginBooksCover', $tester->getDisplay());
    }

    private function command(?ImageService $imageService = null): MediaThumbnailRebuildCommand
    {
        return new MediaThumbnailRebuildCommand($imageService ?? $this->createStub(ImageService::class));
    }
}
