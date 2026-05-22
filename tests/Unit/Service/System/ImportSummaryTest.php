<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Service\System\ImportSummary;
use PHPUnit\Framework\TestCase;

class ImportSummaryTest extends TestCase
{
    public function testConstructorAssignsAllCounters(): void
    {
        // Act
        $summary = new ImportSummary(usersCreated: 1, usersSkipped: 2, locationsCreated: 3, eventsCreated: 4, cmsPagesCreated: 5, cmsPagesSkipped: 6);

        // Assert
        static::assertSame(1, $summary->usersCreated);
        static::assertSame(2, $summary->usersSkipped);
        static::assertSame(3, $summary->locationsCreated);
        static::assertSame(4, $summary->eventsCreated);
        static::assertSame(5, $summary->cmsPagesCreated);
        static::assertSame(6, $summary->cmsPagesSkipped);
    }
}
