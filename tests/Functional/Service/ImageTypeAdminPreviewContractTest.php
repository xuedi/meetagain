<?php declare(strict_types=1);

namespace Tests\Functional\Service;

use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\ThumbnailSizeFormat;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImageTypeAdminPreviewContractTest extends KernelTestCase
{
    public function testEveryRegisteredTypeHasAnAdminPreviewSize(): void
    {
        // Arrange
        self::bootKernel();
        $registry = self::getContainer()->get(ImageTypeRegistry::class);
        $format = self::getContainer()->get(ThumbnailSizeFormat::class);

        // Act & Assert
        foreach ($registry->all() as $definition) {
            $size = $registry->getAdminPreviewSize($definition->getType());
            $parsed = $format->parse($size);
            static::assertNotNull($parsed, $definition::class . ' -> ' . $size);
            static::assertSame(350, $parsed[0], $definition::class . ' -> ' . $size);
        }
    }
}
