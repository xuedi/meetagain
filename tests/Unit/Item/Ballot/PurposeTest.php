<?php declare(strict_types=1);

namespace Tests\Unit\Item\Ballot;

use App\Item\Ballot\Purpose;
use PHPUnit\Framework\TestCase;

class PurposeTest extends TestCase
{
    public function testAnItemTypeSurvivesTheRoundTrip(): void
    {
        // Arrange
        $purpose = new Purpose()->forType('film');

        // Act
        $itemType = new Purpose()->itemTypeOf($purpose);

        // Assert
        self::assertSame('event.item.film', $purpose);
        self::assertSame('film', $itemType);
    }

    public function testAnotherConsumersPurposeIsNotClaimed(): void
    {
        // Arrange & Act & Assert
        self::assertNull(new Purpose()->itemTypeOf('event.location'));
        self::assertNull(new Purpose()->itemTypeOf('contribution.field'));
        self::assertNull(new Purpose()->itemTypeOf('event.item.'));
    }
}
