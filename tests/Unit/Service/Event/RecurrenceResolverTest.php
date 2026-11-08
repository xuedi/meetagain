<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Enum\EventInterval;
use App\Service\Event\RecurrenceResolver;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecurrenceResolverTest extends TestCase
{
    #[DataProvider('ruleProvider')]
    public function testTheRuleResolvesToAPatternOrNothing(?EventInterval $rule, ?string $spec, ?string $expected): void
    {
        // Arrange
        $anchor = new DateTimeImmutable('2026-11-05 19:00');

        // Act
        $pattern = new RecurrenceResolver()->resolve($rule, $spec, $anchor);

        // Assert
        static::assertSame($expected, $pattern?->toRfcString());
    }

    public static function ruleProvider(): iterable
    {
        yield 'no rule' => [null, null, null];
        yield 'a preset takes the anchor day' => [EventInterval::Monthly, null, 'FREQ=MONTHLY;BYMONTHDAY=5'];
        yield 'a preset ignores the spec' => [EventInterval::Daily, 'FREQ=WEEKLY;BYDAY=MO', 'FREQ=DAILY'];
        yield 'a custom rule parses its spec' => [EventInterval::Custom, 'FREQ=WEEKLY;BYDAY=TU', 'FREQ=WEEKLY;BYDAY=TU'];
        yield 'a custom rule without a spec' => [EventInterval::Custom, null, null];
        yield 'a custom rule with an empty spec' => [EventInterval::Custom, '', null];
        yield 'a custom rule with a broken spec' => [EventInterval::Custom, 'FREQ=WEEKLY', null];
    }
}
