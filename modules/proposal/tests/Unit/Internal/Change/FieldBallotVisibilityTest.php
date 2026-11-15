<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Change;

use Module\Ballot\Contract\BallotScope;
use Module\Ballot\Contract\BallotSubject;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Internal\Change\FieldBallotVisibility;
use Module\Proposal\Internal\Change\Registry;
use Module\Proposal\Tests\Stub\Change\Target;
use PHPUnit\Framework\TestCase;

class FieldBallotVisibilityTest extends TestCase
{
    public function testAFieldBallotIsVisibleOnlyWhereItsTargetIs(): void
    {
        // Arrange
        $filter = new FieldBallotVisibility(new Registry([new Target()]));
        $ballots = [
            new BallotScope(1, ChangeInterface::BALLOT_PURPOSE, new BallotSubject(Target::TYPE, 7)),
            new BallotScope(2, ChangeInterface::BALLOT_PURPOSE, new BallotSubject(Target::TYPE, Target::MISSING)),
            new BallotScope(3, ChangeInterface::BALLOT_PURPOSE, new BallotSubject('no_such_target', 7)),
            new BallotScope(4, ChangeInterface::BALLOT_PURPOSE),
        ];

        // Act
        $visible = $filter->narrowVisibleBallotIds(ChangeInterface::BALLOT_PURPOSE, $ballots, 5);

        // Assert
        self::assertSame([1], $visible);
    }

    public function testAnotherPurposeIsLeftToOtherFilters(): void
    {
        // Arrange
        $filter = new FieldBallotVisibility(new Registry([new Target()]));

        // Act
        $verdict = $filter->narrowVisibleBallotIds('item.choice', [new BallotScope(1, 'item.choice')], 5);

        // Assert
        self::assertNull($verdict);
    }
}
