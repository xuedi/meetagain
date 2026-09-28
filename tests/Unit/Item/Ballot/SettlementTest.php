<?php declare(strict_types=1);

namespace Tests\Unit\Item\Ballot;

use App\Item\AssociationService;
use App\Item\Ballot\Purpose;
use App\Item\Ballot\Settlement;
use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettlementTest extends TestCase
{
    public function testTheWinnerIsAttachedToTheEventItWasDecidedFor(): void
    {
        // Arrange
        $associations = $this->associations();
        $associations->expects(self::once())->method('attach')->with(7, 'film', 42, 3);

        // Act & Assert
        new Settlement($associations)->settled($this->outcome('42'));
    }

    public function testATieAttachesNothing(): void
    {
        // Arrange
        $associations = $this->associations();
        $associations->expects(self::never())->method('attach');

        // Act & Assert
        new Settlement($associations)->settled($this->outcome(null, ['42', '43']));
    }

    public function testACandidateThatIsNotAnItemIdAttachesNothing(): void
    {
        // Arrange
        $associations = $this->associations();
        $associations->expects(self::never())->method('attach');

        // Act & Assert
        new Settlement($associations)->settled($this->outcome('undecided'));
    }

    public function testOnlyItemPurposesAreClaimed(): void
    {
        // Arrange
        $settlement = new Settlement($this->createStub(AssociationService::class));

        // Act & Assert
        self::assertTrue($settlement->supports(Purpose::forType('film')));
        self::assertFalse($settlement->supports('event.location'));
    }

    /**
     * @param list<string> $tiedKeys
     */
    private function outcome(?string $winningKey, array $tiedKeys = []): BallotOutcome
    {
        return new BallotOutcome(
            1,
            Purpose::forType('film'),
            BallotStatus::Settled,
            $winningKey,
            $tiedKeys,
            new BallotSubject(Purpose::SUBJECT_TYPE, 7),
            null,
            3,
        );
    }

    private function associations(): AssociationService&MockObject
    {
        return $this->createMock(AssociationService::class);
    }
}
