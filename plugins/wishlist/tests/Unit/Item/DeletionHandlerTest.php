<?php declare(strict_types=1);

namespace Plugin\Wishlist\Tests\Unit\Item;

use App\Enum\ItemAction;
use PHPUnit\Framework\TestCase;
use Plugin\Wishlist\Item\DeletionHandler;
use Plugin\Wishlist\Repository\WishlistEntryRepository;

final class DeletionHandlerTest extends TestCase
{
    public function testADeletedItemTakesItsWishesWithIt(): void
    {
        // Arrange
        $entries = $this->createMock(WishlistEntryRepository::class);
        $entries->expects($this->once())->method('deleteByItem')->with('film', 7);

        // Act
        new DeletionHandler($entries)->onItemAction(ItemAction::Deleted, 'film', 7);
    }

    public function testAnUpdatedItemKeepsItsWishes(): void
    {
        // Arrange
        $entries = $this->createMock(WishlistEntryRepository::class);
        $entries->expects($this->never())->method('deleteByItem');

        // Act
        new DeletionHandler($entries)->onItemAction(ItemAction::Updated, 'film', 7);
    }
}
