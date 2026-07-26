<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Service\Event\RecurrenceDescriber;
use App\ValueObject\RecurrencePattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class RecurrenceDescriberTest extends TestCase
{
    #[DataProvider('provideWeekdaySentences')]
    public function testDescribesWeekdayPatterns(string $spec, string $locale, string $expected): void
    {
        // Arrange
        $describer = new RecurrenceDescriber($this->createTranslator());

        // Act
        $sentence = $describer->describe(RecurrencePattern::fromRfcString($spec), $locale);

        // Assert
        static::assertSame($expected, $sentence);
    }

    public static function provideWeekdaySentences(): iterable
    {
        yield 'weekly en' => ['FREQ=WEEKLY;BYDAY=SU', 'en', 'Every Sunday'];
        yield 'weekly de' => ['FREQ=WEEKLY;BYDAY=SU', 'de', 'Jeden Sonntag'];
        yield 'weekly zh' => ['FREQ=WEEKLY;BYDAY=SU', 'zh', '每星期日'];
        yield 'two weeks en' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=FR', 'en', 'Every Friday, every second week'];
        yield 'first Sunday en' => ['FREQ=MONTHLY;BYDAY=1SU', 'en', 'Every first Sunday of the month'];
        yield 'first Sunday de' => ['FREQ=MONTHLY;BYDAY=1SU', 'de', 'Jeden ersten Sonntag im Monat'];
        yield 'first Sunday zh' => ['FREQ=MONTHLY;BYDAY=1SU', 'zh', '每月第一个星期日'];
        yield 'last Friday en' => ['FREQ=MONTHLY;BYDAY=-1FR', 'en', 'Every last Friday of the month'];
        yield 'second Saturday two months en' => [
            'FREQ=MONTHLY;INTERVAL=2;BYDAY=2SA', 'en', 'Every second Saturday of every second month',
        ];
        yield 'third Monday quarterly en' => [
            'FREQ=MONTHLY;INTERVAL=3;BYDAY=3MO', 'en', 'Every third Monday of every quarter',
        ];
        yield 'fourth Thursday yearly en' => [
            'FREQ=YEARLY;BYMONTH=8;BYDAY=4TH', 'en', 'Every fourth Thursday of August each year',
        ];
        yield 'fourth Thursday yearly de' => [
            'FREQ=YEARLY;BYMONTH=8;BYDAY=4TH', 'de', 'Jeden vierten Donnerstag im August jedes Jahr',
        ];
    }

    #[DataProvider('provideDayOfMonthSentences')]
    public function testDescribesDayOfMonthPatterns(string $spec, string $locale, string $expected): void
    {
        // Arrange
        $describer = new RecurrenceDescriber($this->createTranslator());

        // Act
        $sentence = $describer->describe(RecurrencePattern::fromRfcString($spec), $locale);

        // Assert
        static::assertSame($expected, $sentence);
    }

    public static function provideDayOfMonthSentences(): iterable
    {
        yield 'fifteenth en' => ['FREQ=MONTHLY;BYMONTHDAY=15', 'en', 'Every 15th of the month'];
        yield 'fifteenth de' => ['FREQ=MONTHLY;BYMONTHDAY=15', 'de', 'Jeden 15. des Monats'];
        yield 'fifteenth zh' => ['FREQ=MONTHLY;BYMONTHDAY=15', 'zh', '每月15日'];
        yield 'first en' => ['FREQ=MONTHLY;BYMONTHDAY=1', 'en', 'Every 1st of the month'];
        yield 'second en' => ['FREQ=MONTHLY;BYMONTHDAY=2', 'en', 'Every 2nd of the month'];
        yield 'third en' => ['FREQ=MONTHLY;BYMONTHDAY=3', 'en', 'Every 3rd of the month'];
        yield 'twenty-first en' => ['FREQ=MONTHLY;BYMONTHDAY=21', 'en', 'Every 21st of the month'];
        yield 'thirty-first en' => ['FREQ=MONTHLY;BYMONTHDAY=31', 'en', 'Every 31st of the month'];
        yield 'quarterly en' => ['FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=15', 'en', 'Every 15th of every quarter'];
        yield 'yearly en' => ['FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=15', 'en', 'Every 15th of August each year'];
        yield 'yearly zh' => ['FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=15', 'zh', '每年八月15日'];
        yield 'last day en' => ['FREQ=MONTHLY;BYMONTHDAY=-1', 'en', 'On the last day of the month'];
        yield 'last day de' => ['FREQ=MONTHLY;BYMONTHDAY=-1', 'de', 'Am letzten Tag des Monats'];
        yield 'last day zh' => ['FREQ=MONTHLY;BYMONTHDAY=-1', 'zh', '每月最后一天'];
        yield 'last day yearly en' => [
            'FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=-1', 'en', 'On the last day of August each year',
        ];
    }

    public function testDescribesEveryPeriodAndModeWithoutLeakingAKey(): void
    {
        // Arrange
        $describer = new RecurrenceDescriber($this->createTranslator());
        $patterns = [];
        foreach (RecurrencePeriod::cases() as $period) {
            $anchorMonth = RecurrencePeriod::Year === $period ? 8 : null;
            $ordinal = $period->isWeekly() ? null : RecurrenceOrdinal::First;
            $patterns[] = RecurrencePattern::weekday($period, Weekday::Sunday, $ordinal, $anchorMonth);
            if (!$period->isWeekly()) {
                $patterns[] = RecurrencePattern::dayOfMonth($period, 15, $anchorMonth);
                $patterns[] = RecurrencePattern::dayOfMonth(
                    $period,
                    RecurrencePattern::LAST_DAY_OF_MONTH,
                    $anchorMonth,
                );
            }
        }

        // Act & Assert
        foreach (['en', 'de', 'zh'] as $locale) {
            foreach ($patterns as $pattern) {
                $sentence = $describer->describe($pattern, $locale);
                static::assertStringNotContainsString('admin_event.', $sentence);
                static::assertStringNotContainsString('%', $sentence);
            }
        }
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());

        foreach (['en', 'de', 'zh'] as $locale) {
            $translator->addResource(
                'yaml',
                sprintf('%s/../../../../translations/admin/messages.%s.yaml', __DIR__, $locale),
                $locale,
            );
        }

        return $translator;
    }
}
