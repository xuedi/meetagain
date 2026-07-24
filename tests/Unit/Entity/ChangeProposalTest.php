<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\ChangeProposal;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Review\FieldChange;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChangeProposalTest extends TestCase
{
    public function testChangesRoundTripThroughTheJsonMap(): void
    {
        // Arrange
        $proposal = new ChangeProposal();

        // Act
        $proposal->setChanges([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('category', '3', '5', FieldResolution::Applied),
        ]);

        // Assert
        $changes = $proposal->getChanges();
        self::assertCount(2, $changes);
        self::assertSame('phrase', $changes[0]->field);
        self::assertSame('old', $changes[0]->before);
        self::assertSame('new', $changes[0]->after);
        self::assertNull($changes[0]->resolution);
        self::assertSame(FieldResolution::Applied, $changes[1]->resolution);
    }

    public function testResolveFieldMarksOnlyThatField(): void
    {
        // Arrange
        $proposal = new ChangeProposal();
        $proposal->setChanges([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);

        // Act
        $proposal->resolveField('phrase', FieldResolution::Applied);

        // Assert
        self::assertTrue($proposal->getChange('phrase')->isApplied());
        self::assertFalse($proposal->getChange('pinyin')->isResolved());
        self::assertFalse($proposal->isFullyResolved());
        self::assertCount(1, $proposal->getUnresolvedChanges());
    }

    public function testFullyResolvedAndAppliedDetection(): void
    {
        // Arrange
        $proposal = new ChangeProposal();
        $proposal->setChanges([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);

        // Act
        $proposal->resolveField('phrase', FieldResolution::Denied);
        $proposal->resolveField('pinyin', FieldResolution::Applied);

        // Assert
        self::assertTrue($proposal->isFullyResolved());
        self::assertTrue($proposal->hasAppliedField());
    }

    public function testUnknownFieldThrows(): void
    {
        // Arrange
        $proposal = new ChangeProposal();
        $proposal->setChanges([new FieldChange('phrase', 'old', 'new')]);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $proposal->resolveField('missing', FieldResolution::Applied);
    }

    public function testFreshProposalStartsPending(): void
    {
        // Arrange + Act
        $proposal = new ChangeProposal();

        // Assert
        self::assertSame(ChangeProposalStatus::Pending, $proposal->getStatus());
        self::assertTrue($proposal->isPending());
        self::assertNull($proposal->getReviewedBy());
        self::assertNull($proposal->getReviewedAt());
    }
}
