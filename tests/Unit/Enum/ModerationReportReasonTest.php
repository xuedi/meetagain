<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\ModerationReportReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModerationReportReasonTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabelReturnsTranslationKey(ModerationReportReason $reason, string $expected): void
    {
        // Act
        $actual = $reason->label();

        // Assert
        static::assertSame($expected, $actual);
    }

    public static function provideLabelCases(): iterable
    {
        yield 'spam' => [ModerationReportReason::Spam, 'report.subject_reason_spam'];
        yield 'harassment' => [ModerationReportReason::Harassment, 'report.subject_reason_harassment'];
        yield 'inappropriate' => [ModerationReportReason::Inappropriate, 'report.subject_reason_inappropriate'];
        yield 'impersonation' => [ModerationReportReason::Impersonation, 'report.subject_reason_impersonation'];
        yield 'other' => [ModerationReportReason::Other, 'report.subject_reason_other'];
    }
}
