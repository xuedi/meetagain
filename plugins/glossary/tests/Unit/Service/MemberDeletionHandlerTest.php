<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\Enum\EntityAction;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Repository\TrainerCardRepository;
use Plugin\Glossary\Repository\TrainerDayRepository;
use Plugin\Glossary\Service\MemberDeletionHandler;

class MemberDeletionHandlerTest extends TestCase
{
    public function testADeletedMemberTakesTheirTrainerRowsWithThem(): void
    {
        // Arrange
        $cards = $this->createMock(TrainerCardRepository::class);
        $cards->expects(self::once())->method('deleteForUser')->with(12);
        $days = $this->createMock(TrainerDayRepository::class);
        $days->expects(self::once())->method('deleteForUser')->with(12);

        // Act
        new MemberDeletionHandler($cards, $days)->onEntityAction(EntityAction::EraseUser, 12);
    }

    public function testOtherActionsLeaveTrainerRowsAlone(): void
    {
        // Arrange
        $cards = $this->createMock(TrainerCardRepository::class);
        $cards->expects(self::never())->method('deleteForUser');
        $days = $this->createMock(TrainerDayRepository::class);
        $days->expects(self::never())->method('deleteForUser');

        // Act
        new MemberDeletionHandler($cards, $days)->onEntityAction(EntityAction::DeleteGlossary, 12);
    }
}
