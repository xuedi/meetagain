<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use InvalidArgumentException;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\TrustBand;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrustConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('provideInvalidSettings')]
    public function testOutOfRangeSettingsAreRejected(array $overrides): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);

        // Act & Assert
        new TrustConfig(...$overrides);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidSettings(): iterable
    {
        yield 'percentage above 100' => [['percentTrusted' => 101]];
        yield 'negative percentage' => [['percentSlight' => -1]];
        yield 'negative action points' => [['pointsPerAction' => ['handover' => -5]]];
        yield 'action points above the cap' => [['maxScore' => 100, 'pointsPerAction' => ['donation' => 500]]];
        yield 'negative action cap' => [['capsPerAction' => ['tenure' => -1]]];
        yield 'empty action key' => [['pointsPerAction' => ['' => 5]]];
        yield 'minimum above the cap' => [['maxScore' => 100, 'minimumToParticipate' => 500]];
        yield 'band thresholds out of order' => [['bandThresholds' => [500, 200, 50]]];
        yield 'wrong number of band thresholds' => [['bandThresholds' => [50, 200]]];
    }

    #[DataProvider('provideBands')]
    public function testBandsFollowTheThresholds(int $score, TrustBand $expected): void
    {
        // Arrange
        $config = new TrustConfig();

        // Act
        $band = $config->bandFor($score);

        // Assert
        self::assertSame($expected, $band);
    }

    /**
     * @return iterable<string, array{int, TrustBand}>
     */
    public static function provideBands(): iterable
    {
        yield 'below the first threshold' => [49, TrustBand::Newcomer];
        yield 'exactly the first threshold' => [50, TrustBand::Known];
        yield 'exactly the second threshold' => [200, TrustBand::Trusted];
        yield 'exactly the third threshold' => [500, TrustBand::Highly];
        yield 'far above every threshold' => [1000, TrustBand::Highly];
    }

    public function testPercentagesMapToTheirSettings(): void
    {
        // Arrange
        $config = new TrustConfig(percentSlight: 11, percentTrusted: 22, percentAbsolute: 33);

        // Act & Assert
        self::assertSame(11, $config->percentFor(TrustLevel::Slight));
        self::assertSame(22, $config->percentFor(TrustLevel::Trusted));
        self::assertSame(33, $config->percentFor(TrustLevel::Absolute));
    }

    public function testAnActionFallsBackToItsDescriptorUntilOverridden(): void
    {
        // Arrange
        $descriptor = new ActionDescriptor('handover', 'label', 5, 12);
        $defaults = new TrustConfig();
        $overridden = new TrustConfig(pointsPerAction: ['handover' => 40], capsPerAction: ['handover' => 3]);

        // Act & Assert
        self::assertSame(5, $defaults->pointsFor($descriptor));
        self::assertSame(12, $defaults->capFor($descriptor));
        self::assertSame(40, $overridden->pointsFor($descriptor));
        self::assertSame(3, $overridden->capFor($descriptor));
    }

    public function testAnActionNobodyConfiguredIsUncappedByDefault(): void
    {
        // Arrange
        $descriptor = new ActionDescriptor('donation', 'label', 25);

        // Act & Assert
        self::assertNull(new TrustConfig()->capFor($descriptor));
    }
}
