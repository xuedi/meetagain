<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\ImageReportReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class ImageReportReasonTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabelReturnsTranslationKey(ImageReportReason $reason, string $expected): void
    {
        // Act
        $actual = $reason->label();

        // Assert
        static::assertSame($expected, $actual);
    }

    public static function provideLabelCases(): iterable
    {
        yield 'privacy'       => [ImageReportReason::Privacy,       'report.reason_privacy'];
        yield 'copyright'     => [ImageReportReason::Copyright,     'report.reason_copyright'];
        yield 'inappropriate' => [ImageReportReason::Inappropriate, 'report.reason_inappropriate'];
        yield 'irrelevant'    => [ImageReportReason::Irrelevant,    'report.reason_irrelevant'];
    }

    public function testGetChoicesReturnsAllCasesKeyedByTranslationKey(): void
    {
        // Arrange
        $translator = new IdentityTranslator();

        // Act
        $choices = ImageReportReason::getChoices($translator);

        // Assert
        static::assertSame(ImageReportReason::Privacy,       $choices['report.reason_privacy']);
        static::assertSame(ImageReportReason::Copyright,     $choices['report.reason_copyright']);
        static::assertSame(ImageReportReason::Inappropriate, $choices['report.reason_inappropriate']);
        static::assertSame(ImageReportReason::Irrelevant,    $choices['report.reason_irrelevant']);
        static::assertCount(4, $choices);
    }

    public function testGetTranslatedListReturnsValueToKeyMap(): void
    {
        // Arrange
        $translator = new IdentityTranslator();

        // Act
        $list = ImageReportReason::getTranslatedList($translator);

        // Assert - keyed by enum value (int), value is the translation key
        static::assertSame('report.reason_privacy',       $list[ImageReportReason::Privacy->value]);
        static::assertSame('report.reason_copyright',     $list[ImageReportReason::Copyright->value]);
        static::assertSame('report.reason_inappropriate', $list[ImageReportReason::Inappropriate->value]);
        static::assertSame('report.reason_irrelevant',    $list[ImageReportReason::Irrelevant->value]);
    }
}
