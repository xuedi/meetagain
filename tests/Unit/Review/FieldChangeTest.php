<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Enum\FieldResolution;
use App\Review\FieldChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldChangeTest extends TestCase
{
    #[DataProvider('roundTripCases')]
    public function testArrayRoundTripKeepsAllSlots(?string $before, ?string $after, ?FieldResolution $resolution): void
    {
        // Arrange
        $change = new FieldChange('phrase', $before, $after, $resolution);

        // Act
        $rebuilt = FieldChange::fromArray('phrase', $change->toArray());

        // Assert
        self::assertSame('phrase', $rebuilt->field);
        self::assertSame($before, $rebuilt->before);
        self::assertSame($after, $rebuilt->after);
        self::assertSame($resolution, $rebuilt->resolution);
    }

    public static function roundTripCases(): iterable
    {
        yield 'unresolved change' => ['old', 'new', null];
        yield 'applied change' => ['old', 'new', FieldResolution::Applied];
        yield 'denied change' => ['old', 'new', FieldResolution::Denied];
        yield 'null before means the value was unset' => [null, 'new', null];
        yield 'null after means the value is cleared' => ['old', null, null];
    }

    public function testResolutionStateAccessors(): void
    {
        // Arrange
        $unresolved = new FieldChange('phrase', 'a', 'b');
        $applied = new FieldChange('phrase', 'a', 'b', FieldResolution::Applied);
        $denied = new FieldChange('phrase', 'a', 'b', FieldResolution::Denied);

        // Assert
        self::assertFalse($unresolved->isResolved());
        self::assertFalse($unresolved->isApplied());
        self::assertTrue($applied->isResolved());
        self::assertTrue($applied->isApplied());
        self::assertTrue($denied->isResolved());
        self::assertFalse($denied->isApplied());
    }
}
