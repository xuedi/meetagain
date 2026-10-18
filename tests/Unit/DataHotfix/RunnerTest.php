<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\DataHotfixInterface;
use App\DataHotfix\Runner;
use App\Service\AppStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;

class RunnerTest extends TestCase
{
    public function testAPluginHotfixSortsBetweenCoreHotfixesByItsDate(): void
    {
        // Arrange
        $runner = new Runner(
            [$this->hotfix('2026_03_01_core_later'), $this->hotfix('plugin_2026_02_01_middle'), $this->hotfix('2026_01_01_core_earlier')],
            $this->createStub(AppStateService::class),
            $this->createStub(ClockInterface::class),
        );

        // Act
        $identifiers = array_map(static fn(DataHotfixInterface $hotfix): string => $hotfix->getIdentifier(), $runner->all());

        // Assert
        static::assertSame(['2026_01_01_core_earlier', 'plugin_2026_02_01_middle', '2026_03_01_core_later'], $identifiers);
    }

    public function testHotfixesOfTheSameDayKeepCoreFirst(): void
    {
        // Arrange
        $runner = new Runner(
            [$this->hotfix('plugin_2026_08_01_scoped'), $this->hotfix('2026_08_01_core')],
            $this->createStub(AppStateService::class),
            $this->createStub(ClockInterface::class),
        );

        // Act
        $identifiers = array_map(static fn(DataHotfixInterface $hotfix): string => $hotfix->getIdentifier(), $runner->all());

        // Assert
        static::assertSame(['2026_08_01_core', 'plugin_2026_08_01_scoped'], $identifiers);
    }

    private function hotfix(string $identifier): DataHotfixInterface
    {
        $hotfix = $this->createStub(DataHotfixInterface::class);
        $hotfix->method('getIdentifier')->willReturn($identifier);

        return $hotfix;
    }
}
