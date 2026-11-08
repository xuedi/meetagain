<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Enum\SecurityMeasure;
use App\Service\Config\ConfigService;
use App\Service\Security\MeasureSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeasureSettingsTest extends TestCase
{
    #[DataProvider('measureProvider')]
    public function testAnUnsetMeasureFallsBackToItsOwnDefault(SecurityMeasure $measure): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getBoolean')->willReturnCallback(static fn(string $name, bool $default): bool => $default);

        // Act
        $enabled = new MeasureSettings($config)->isEnabled($measure);

        // Assert
        static::assertSame($measure->defaultEnabled(), $enabled);
    }

    public static function measureProvider(): iterable
    {
        foreach (SecurityMeasure::cases() as $measure) {
            yield $measure->value => [$measure];
        }
    }

    public function testSwitchingAMeasureWritesItsConfigKey(): void
    {
        // Arrange
        $written = [];
        $config = $this->createStub(ConfigService::class);
        $config
            ->method('setBoolean')
            ->willReturnCallback(static function (string $name, bool $value) use (&$written): void {
                $written[$name] = $value;
            });

        // Act
        new MeasureSettings($config)->setEnabled(SecurityMeasure::Honeypot, false);

        // Assert
        static::assertSame(['security_measure_honeypot' => false], $written);
    }

    #[DataProvider('clampProvider')]
    public function testStoredValuesAreClampedToTheirRange(string $key, int $stored, string $getter, int $expected): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getInt')->willReturnCallback(static fn(string $name, int $default): int => $name === $key ? $stored : $default);

        // Act
        $value = new MeasureSettings($config)->{$getter}();

        // Assert
        static::assertSame($expected, $value);
    }

    public static function clampProvider(): iterable
    {
        yield 'difficulty below the floor' => ['security_pow_difficulty', 2, 'proofOfWorkDifficulty', 8];
        yield 'difficulty inside the range' => ['security_pow_difficulty', 20, 'proofOfWorkDifficulty', 20];
        yield 'difficulty above the ceiling' => ['security_pow_difficulty', 40, 'proofOfWorkDifficulty', 24];
        yield 'retention below the floor' => ['security_measure_log_retention_days', 0, 'logRetentionDays', 1];
        yield 'retention inside the range' => ['security_measure_log_retention_days', 90, 'logRetentionDays', 90];
        yield 'retention above the ceiling' => ['security_measure_log_retention_days', 1000, 'logRetentionDays', 365];
    }

    #[DataProvider('saveProvider')]
    public function testSavingWritesBothValues(array $formData, array $expected): void
    {
        // Arrange
        $written = [];
        $config = $this->createStub(ConfigService::class);
        $config
            ->method('setInt')
            ->willReturnCallback(static function (string $name, int $value) use (&$written): void {
                $written[$name] = $value;
            });

        // Act
        new MeasureSettings($config)->save($formData);

        // Assert
        static::assertSame($expected, $written);
    }

    public static function saveProvider(): iterable
    {
        yield 'the submitted values' => [
            ['powDifficulty' => '20', 'logRetentionDays' => '90'],
            ['security_pow_difficulty' => 20, 'security_measure_log_retention_days' => 90],
        ];
        yield 'the defaults for missing keys' => [
            [],
            ['security_pow_difficulty' => 18, 'security_measure_log_retention_days' => 30],
        ];
    }
}
