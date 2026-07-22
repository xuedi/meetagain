<?php declare(strict_types=1);

namespace Tests\Functional\Service;

use App\Service\Media\ImageTypes\ImageTypeRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImageTypeAdminPreviewContractTest extends KernelTestCase
{
    public function testEveryRegisteredTypeHasAnAdminPreviewSize(): void
    {
        // Arrange
        self::bootKernel();
        $registry = self::getContainer()->get(ImageTypeRegistry::class);

        // Act & Assert - getAdminPreviewSize() throws for any definition without a 350-width entry
        foreach ($registry->all() as $definition) {
            $size = $registry->getAdminPreviewSize($definition->getType());
            static::assertStringStartsWith('350x', $size, $definition::class);
        }
    }
}
