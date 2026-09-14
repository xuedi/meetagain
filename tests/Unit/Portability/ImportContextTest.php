<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\ImageImporter;
use App\Portability\ImportContext;
use PHPUnit\Framework\TestCase;

final class ImportContextTest extends TestCase
{
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
        $imageImporter->expects($this->once())->method('import')->with('/archive/images/a.jpg', ImageType::EventTeaser, $systemUser)->willReturn(null);

        // Act
        new ImportContext($imageImporter, '/archive', $systemUser)->importImage('images/a.jpg', ImageType::EventTeaser);
    }

    private function context(User $systemUser, Image $image): ImportContext
    {
        $imageImporter = $this->createStub(ImageImporter::class);
        $imageImporter->method('import')->willReturn($image);

        return new ImportContext($imageImporter, '/archive', $systemUser);
    }
}
