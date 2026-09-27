<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Contract;

use Module\Email\Contract\DeliveryLogCollection;
use PHPUnit\Framework\TestCase;

final class DeliveryLogCollectionTest extends TestCase
{
    public function testAPageWithoutItemsIsEmptyWhateverTheTotalSays(): void
    {
        // Arrange
        $collection = new DeliveryLogCollection([], 40, 40, 20);

        // Act
        $empty = $collection->isEmpty();

        // Assert
        static::assertTrue($empty);
    }
}
