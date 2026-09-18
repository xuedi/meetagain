<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\SecurityMeasure;
use PHPUnit\Framework\TestCase;

final class SecurityMeasureTest extends TestCase
{
    public function testEveryMeasureOwnsADistinctConfigKey(): void
    {
        // Act
        $keys = SecurityMeasure::configKeys();

        // Assert
        static::assertCount(count(SecurityMeasure::cases()), $keys);
        static::assertSame($keys, array_values(array_unique($keys)));
        foreach ($keys as $key) {
            static::assertStringStartsWith('security_measure_', $key);
        }
    }

    public function testProofOfWorkIsTheOnlyMeasureThatShipsDisabled(): void
    {
        // Act
        $disabled = array_filter(SecurityMeasure::cases(), static fn(SecurityMeasure $m): bool => !$m->defaultEnabled());

        // Assert
        static::assertSame([SecurityMeasure::ProofOfWork], array_values($disabled));
    }

    public function testEveryMeasureIsClassifiedAsFormBasedOrNot(): void
    {
        // Act & Assert
        foreach (SecurityMeasure::cases() as $measure) {
            static::assertIsBool($measure->isFormMeasure());
        }

        static::assertNotEmpty(SecurityMeasure::formMeasures());
    }

    public function testEveryMeasureHasItsOwnLabelDescriptionIconAndColour(): void
    {
        // Act
        $labels = array_map(static fn(SecurityMeasure $m): string => $m->labelKey(), SecurityMeasure::cases());
        $descriptions = array_map(static fn(SecurityMeasure $m): string => $m->descriptionKey(), SecurityMeasure::cases());
        $colors = array_map(static fn(SecurityMeasure $m): string => $m->color(), SecurityMeasure::cases());
        $icons = array_map(static fn(SecurityMeasure $m): string => $m->icon(), SecurityMeasure::cases());

        // Assert
        static::assertSame($labels, array_values(array_unique($labels)));
        static::assertSame($descriptions, array_values(array_unique($descriptions)));
        static::assertSame($colors, array_values(array_unique($colors)));
        static::assertSame($icons, array_values(array_unique($icons)));
    }
}
