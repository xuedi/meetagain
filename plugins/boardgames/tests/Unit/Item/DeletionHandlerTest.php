<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Item;

use App\Enum\ItemAction;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Item\DeletionHandler;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Repository\GamePledgeRepository;

class DeletionHandlerTest extends TestCase
{
    public function testDeletingAGameClearsOwnershipsPledgesAndRequests(): void
    {
        // Arrange
        $ownerships = $this->createMock(GameOwnershipRepository::class);
        $ownerships->expects(self::once())->method('deleteForGameIds')->with([9]);
        $pledges = $this->createMock(GamePledgeRepository::class);
        $pledges->expects(self::once())->method('deleteForGameIds')->with([9]);
        $requests = $this->createMock(BringRequestRepository::class);
        $requests->expects(self::once())->method('deleteForGameIds')->with([9]);
        $handler = new DeletionHandler($ownerships, $pledges, $requests);

        // Act
        $handler->onItemAction(ItemAction::Deleted, 'boardgame', 9);
    }

    public function testAnotherItemTypeIsIgnored(): void
    {
        // Arrange
        $ownerships = $this->createMock(GameOwnershipRepository::class);
        $ownerships->expects(self::never())->method('deleteForGameIds');
        $handler = $this->handler($ownerships);

        // Act
        $handler->onItemAction(ItemAction::Deleted, 'book', 9);
    }

    public function testANonDeleteActionIsIgnored(): void
    {
        // Arrange
        $ownerships = $this->createMock(GameOwnershipRepository::class);
        $ownerships->expects(self::never())->method('deleteForGameIds');
        $handler = $this->handler($ownerships);

        // Act
        $handler->onItemAction(ItemAction::Updated, 'boardgame', 9);
    }

    private function handler(GameOwnershipRepository $ownerships): DeletionHandler
    {
        return new DeletionHandler(
            $ownerships,
            $this->createStub(GamePledgeRepository::class),
            $this->createStub(BringRequestRepository::class),
        );
    }
}
