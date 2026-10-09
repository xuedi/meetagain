<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\ModerationReportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModerationReportStatusTest extends TestCase
{
    #[DataProvider('provideStatusCases')]
    public function testLabelAndTagVariant(ModerationReportStatus $status, string $expectedLabel, string $expectedVariant): void
    {
        // Act
        $label = $status->label();
        $variant = $status->tagVariant();

        // Assert
        static::assertSame($expectedLabel, $label);
        static::assertSame($expectedVariant, $variant);
    }

    public static function provideStatusCases(): iterable
    {
        yield 'open waits on an admin' => [ModerationReportStatus::Open, 'admin_support_moderation.status_open', 'is-warning'];
        yield 'dismissed is finished' => [ModerationReportStatus::Dismissed, 'admin_support_moderation.status_dismissed', 'is-light'];
        yield 'actioned is done' => [ModerationReportStatus::Actioned, 'admin_support_moderation.status_actioned', 'is-success'];
    }
}
