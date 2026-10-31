<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Module\Geo\Contract\DirectoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EmptyDirectoryTest extends KernelTestCase
{
    public function testAnEmptyDirectoryHoldsNoCity(): void
    {
        // Arrange
        self::bootKernel();
        $directory = self::getContainer()->get(DirectoryInterface::class);

        // Act
        $answers = [$directory->hasCities(), $directory->searchCities('berlin', null, 'en'), $directory->findPlaceNamed('berlin', 'en')];

        // Assert
        self::assertSame([false, [], null], $answers);
    }
}
