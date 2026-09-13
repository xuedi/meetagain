<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Portability\ImportSummary;
use App\Portability\Outcome;
use PHPUnit\Framework\TestCase;

final class ImportSummaryTest extends TestCase
{
    public function testAnUncountedOutcomeReadsAsZero(): void
    {
        // Arrange
        $summary = new ImportSummary(['users' => ['created' => 3]]);

        // Act
        $matched = $summary->get('users', Outcome::Matched);

        // Assert
        static::assertSame(3, $summary->get('users', Outcome::Created));
        static::assertSame(0, $matched);
        static::assertSame(0, $summary->get('events', Outcome::Created));
    }

    public function testLossesAddSkippedAndDroppedRowsPerKind(): void
    {
        // Arrange
        $summary = new ImportSummary([
            'users' => ['created' => 3, 'matched' => 1],
            'cms_pages' => ['created' => 1, 'skipped' => 2],
            'tag_assignments' => ['dropped' => 4],
        ]);

        // Act
        $losses = $summary->getLosses();

        // Assert
        static::assertSame(['cms_pages' => 2, 'tag_assignments' => 4], $losses);
    }
}
