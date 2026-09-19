<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\ImageImporter;
use App\Portability\ImportContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImportContextTest extends TestCase
{
    private string $archiveDir = '';

    protected function setUp(): void
    {
        $this->archiveDir = sys_get_temp_dir() . '/import-context-' . uniqid('', true);
        mkdir($this->archiveDir . '/images', 0o777, true);
        file_put_contents($this->archiveDir . '/images/a.jpg', 'pretend-jpeg');
    }

    protected function tearDown(): void
    {
        foreach (['/images/a.jpg', '/images', ''] as $suffix) {
            $path = $this->archiveDir . $suffix;
            if (is_dir($path)) {
                rmdir($path);
                continue;
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAnImageCreditedOnlyToTheImportAccountTakesTheUploaderARowNames(): void
    {
        // Arrange
        $systemUser = new User();
        $member = new User();
        $image = new Image()->setUploader($systemUser);

        // Act
        $imported = $this->context($systemUser, $image)->importImage('images/a.jpg', ImageType::PluginDishesPreview, $member);

        // Assert
        static::assertSame($member, $imported?->getUploader());
    }

    public function testAnImageWithARealUploaderKeepsIt(): void
    {
        // Arrange
        $systemUser = new User();
        $owner = new User();
        $image = new Image()->setUploader($owner);

        // Act
        $imported = $this->context($systemUser, $image)->importImage('images/a.jpg', ImageType::PluginDishesPreview, new User());

        // Assert
        static::assertSame($owner, $imported?->getUploader());
    }

    public function testWithoutANamedUploaderTheImportAccountIsCredited(): void
    {
        // Arrange
        $systemUser = new User();
        $imageImporter = $this->createMock(ImageImporter::class);
        $imageImporter
            ->expects($this->once())
            ->method('import')
            ->with($this->archiveDir . '/images/a.jpg', ImageType::EventTeaser, $systemUser)
            ->willReturn(null);

        // Act
        $this->subject($imageImporter, $systemUser)->importImage('images/a.jpg', ImageType::EventTeaser);
    }

    #[DataProvider('traversingPathProvider')]
    public function testAPathPointingOutsideTheArchiveAbortsTheImport(string $path): void
    {
        // Arrange
        $imageImporter = $this->createMock(ImageImporter::class);
        $imageImporter->expects($this->never())->method('import');

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $this->subject($imageImporter, new User())->importImage($path, ImageType::EventTeaser);
    }

    public static function traversingPathProvider(): iterable
    {
        yield 'climbing out of the extraction directory' => ['../../../../etc/passwd'];
        yield 'climbing out and back in' => ['images/../../secrets/.env.local.php'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'a null byte truncating the extension' => ["images/a.jpg\0.php"];
    }

    public function testAPathTheArchiveSimplyDoesNotCarryIsStillHandedOn(): void
    {
        // Arrange
        $imageImporter = $this->createMock(ImageImporter::class);
        $imageImporter
            ->expects($this->once())
            ->method('import')
            ->with($this->archiveDir . '/images/gone.jpg')
            ->willReturn(null);

        // Act
        $imported = $this->subject($imageImporter, new User())->importImage('images/gone.jpg', ImageType::EventTeaser);

        // Assert
        static::assertNull($imported);
    }

    public function testASymlinkLeadingOutOfTheArchiveAbortsTheImport(): void
    {
        // Arrange
        symlink('/etc/passwd', $this->archiveDir . '/images/escape.jpg');

        $imageImporter = $this->createMock(ImageImporter::class);
        $imageImporter->expects($this->never())->method('import');

        // Assert
        $this->expectException(RuntimeException::class);

        try {
            // Act
            $this->subject($imageImporter, new User())->importImage('images/escape.jpg', ImageType::EventTeaser);
        } finally {
            unlink($this->archiveDir . '/images/escape.jpg');
        }
    }

    private function context(User $systemUser, Image $image): ImportContext
    {
        $imageImporter = $this->createStub(ImageImporter::class);
        $imageImporter->method('import')->willReturn($image);

        return $this->subject($imageImporter, $systemUser);
    }

    private function subject(ImageImporter $imageImporter, User $systemUser): ImportContext
    {
        return new ImportContext($imageImporter, $this->archiveDir, $systemUser);
    }
}
