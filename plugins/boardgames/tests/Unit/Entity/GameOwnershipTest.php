<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Entity\GameOwnership;

class GameOwnershipTest extends TestCase
{
    public function testNeutralDefaultIsPublicAndBringable(): void
    {
        // Arrange + Act
        $ownership = new GameOwnership();

        // Assert
        static::assertTrue($ownership->isPublic());
        static::assertTrue($ownership->isWillingToBring());
        static::assertFalse($ownership->isCanTeach());
    }

    #[DataProvider('provideVisibilityMatrix')]
    public function testAskabilityMatrix(bool $isPublic, bool $willingToBring, bool $expected): void
    {
        // Arrange
        $ownership = new GameOwnership();

        // Act
        $ownership->setPublic($isPublic)->setWillingToBring($willingToBring);

        // Assert
        static::assertSame($expected, $ownership->isAskable());
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function provideVisibilityMatrix(): iterable
    {
        yield 'public and willing is askable' => [true, true, true];
        yield 'public but not willing is not askable' => [true, false, false];
        yield 'private but willing is not askable' => [false, true, false];
        yield 'private and not willing is not askable' => [false, false, false];
    }
}
