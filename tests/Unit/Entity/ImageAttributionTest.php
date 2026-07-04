<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Image;
use App\Enum\AttributionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageAttributionTest extends TestCase
{
    #[DataProvider('attributionStatusProvider')]
    public function testAttributionStatusIsDerivedFromFields(?string $attribution, bool $notRequired, AttributionStatus $expected): void
    {
        // Arrange
        $image = new Image();
        $image->setAttribution($attribution);
        $image->setAttributionNotRequired($notRequired);

        // Act
        $status = $image->getAttributionStatus();

        // Assert
        static::assertSame($expected, $status);
    }

    /**
     * @return iterable<string, array{?string, bool, AttributionStatus}>
     */
    public static function attributionStatusProvider(): iterable
    {
        yield 'no attribution, not flagged => pending' => [null, false, AttributionStatus::Pending];
        yield 'empty attribution => pending' => ['', false, AttributionStatus::Pending];
        yield 'attribution set => provided' => ['Photo by Jane Doe', false, AttributionStatus::Provided];
        yield 'flag wins over empty => not required' => [null, true, AttributionStatus::NotRequired];
        yield 'flag wins over text => not required' => ['Photo by Jane Doe', true, AttributionStatus::NotRequired];
    }

    public function testAttributionDefaultsToNullAndPending(): void
    {
        // Arrange
        $image = new Image();

        // Act & Assert
        static::assertNull($image->getAttribution());
        static::assertFalse($image->isAttributionNotRequired());
        static::assertSame(AttributionStatus::Pending, $image->getAttributionStatus());
    }
}
