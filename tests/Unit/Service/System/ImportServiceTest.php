<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use App\Service\System\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImportServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/app';

    public function testImportImageReturnsNullWhenSourcePathDoesNotExist(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);
        $service = $this->buildService($fs);

        // Act
        $result = $this->invokeImportImage($service, '/missing.png');

        // Assert
        static::assertNull($result);
    }

    public function testImportImageReturnsExistingImageWhenHashAlreadyPersisted(): void
    {
        // Arrange
        $existing = new Image();
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('fake-image-bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $service = $this->buildService($fs, $em);

        // Act
        $result = $this->invokeImportImage($service, '/source.png');

        // Assert
        static::assertSame($existing, $result);
    }

    public function testImportImageReturnsNullWhenExtensionIsEmpty(): void
    {
        // Arrange - file exists, hash not known, but path has no extension
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $service = $this->buildService($fs, $em);

        // Act - path "/source-no-extension" → pathinfo extension is empty
        $result = $this->invokeImportImage($service, '/source-no-extension');

        // Assert
        static::assertNull($result);
    }

    public function testImportImagePersistsAndWritesNewImage(): void
    {
        // Arrange
        $bytes = 'png-bytes';
        $expectedHash = sha1($bytes);
        $writtenPath = null;
        $persisted = null;

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);
        $fs->method('isDirectory')->willReturn(true);
        $fs->method('putFileContents')->willReturnCallback(static function (string $path) use (&$writtenPath): bool {
            $writtenPath = $path;
            return true;
        });

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof Image) {
                $persisted = $entity;
            }
        });

        $service = $this->buildService($fs, $em);

        // Act
        $result = $this->invokeImportImage($service, '/source.png');

        // Assert
        static::assertNotNull($result);
        static::assertSame($expectedHash, $result->getHash());
        static::assertSame('png', $result->getExtension());
        static::assertSame(strlen($bytes), $result->getSize());
        static::assertSame($result, $persisted);
        static::assertSame(self::PROJECT_DIR . '/data/images/' . $expectedHash . '.png', $writtenPath);
    }

    public function testRemoveDirectoryIsNoOpWhenPathIsNotADirectory(): void
    {
        // Arrange
        $deleteCalls = 0;
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isDirectory')->willReturn(false);
        $fs->method('deleteFile')->willReturnCallback(static function () use (&$deleteCalls): bool {
            $deleteCalls++;
            return true;
        });

        $service = $this->buildService($fs);

        // Act
        $this->invokeRemoveDirectory($service, '/not-a-dir');

        // Assert
        static::assertSame(0, $deleteCalls);
    }

    public function testRemoveDirectoryRecursesAndDeletesEntries(): void
    {
        // Arrange - layout: /root/{.,..,a.txt,sub/{.,..,b.txt}}
        $deletedFiles = [];
        $removedDirs = [];

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => in_array($path, ['/root', '/root/sub'], true),
        );
        $fs->method('scanDirectory')->willReturnCallback(static fn (string $path): array => match ($path) {
            '/root' => ['.', '..', 'a.txt', 'sub'],
            '/root/sub' => ['.', '..', 'b.txt'],
            default => [],
        });
        $fs->method('deleteFile')->willReturnCallback(static function (string $path) use (&$deletedFiles): bool {
            $deletedFiles[] = $path;
            return true;
        });
        $fs->method('removeDirectory')->willReturnCallback(static function (string $path) use (&$removedDirs): bool {
            $removedDirs[] = $path;
            return true;
        });

        $service = $this->buildService($fs);

        // Act
        $this->invokeRemoveDirectory($service, '/root');

        // Assert
        static::assertSame(['/root/a.txt', '/root/sub/b.txt'], $deletedFiles);
        // Subdir removed before root
        static::assertSame(['/root/sub', '/root'], $removedDirs);
    }

    private function buildService(ExtendedFilesystem $fs, ?EntityManagerInterface $em = null): ImportService
    {
        return new ImportService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            userRepository: $this->createStub(UserRepository::class),
            locationRepository: $this->createStub(LocationRepository::class),
            fs: $fs,
            projectDir: self::PROJECT_DIR,
        );
    }

    private function invokeImportImage(ImportService $service, string $path): ?Image
    {
        $method = new ReflectionMethod($service, 'importImage');
        return $method->invoke($service, $path, ImageType::EventUpload, new User());
    }

    private function invokeRemoveDirectory(ImportService $service, string $path): void
    {
        $method = new ReflectionMethod($service, 'removeDirectory');
        $method->invoke($service, $path);
    }
}
