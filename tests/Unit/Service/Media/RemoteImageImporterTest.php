<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageService;
use App\Service\Media\RemoteImageImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RemoteImageImporterTest extends TestCase
{
    public function testABodyOverTheCapIsRefusedAndLeavesNoTempFile(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('upload');
        $oversized = static function (): iterable {
            for ($sent = 0; $sent <= RemoteImageImporter::MAX_BYTES; $sent += 1_048_576) {
                yield str_repeat('a', 1_048_576);
            }
        };
        $importer = new RemoteImageImporter(new MockHttpClient(new MockResponse($oversized())), $imageService, new NullLogger());
        $before = $this->tempFiles();

        // Act
        $image = $importer->import('https://example.test/huge.jpg', $this->createStub(User::class), ImageType::PluginBooksCover);

        // Assert
        static::assertNull($image);
        static::assertSame($before, $this->tempFiles());
    }

    public function testAnErrorStatusIsRefusedAndLeavesNoTempFile(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('upload');
        $importer = new RemoteImageImporter(new MockHttpClient(new MockResponse('', ['http_code' => 404])), $imageService, new NullLogger());
        $before = $this->tempFiles();

        // Act
        $image = $importer->import('https://example.test/missing.jpg', $this->createStub(User::class), ImageType::PluginBooksCover);

        // Assert
        static::assertNull($image);
        static::assertSame($before, $this->tempFiles());
    }

    public function testAFailedUploadStillRemovesTheTempFile(): void
    {
        // Arrange
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('upload')->willThrowException(new RuntimeException('boom'));
        $importer = new RemoteImageImporter(new MockHttpClient(new MockResponse('jpegbytes')), $imageService, new NullLogger());
        $before = $this->tempFiles();

        // Act
        $image = $importer->import('https://example.test/cover.jpg', $this->createStub(User::class), ImageType::PluginBooksCover);

        // Assert
        static::assertNull($image);
        static::assertSame($before, $this->tempFiles());
    }

    /** @return list<string> */
    private function tempFiles(): array
    {
        return array_values(glob(sys_get_temp_dir() . '/remote_*') ?: []);
    }
}
