<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\DataHotfixBaselineCommand;
use App\DataHotfix\DataHotfixInterface;
use App\DataHotfix\Runner;
use App\Service\AppStateService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DataHotfixBaselineCommandTest extends TestCase
{
    public function testEveryHotfixIsRecordedWithoutRunning(): void
    {
        // Arrange
        $appState = $this->makeAppStateSpy();
        $tester = $this->tester([new ExplodingHotfix('2026_01_01_a'), new ExplodingHotfix('plugin_2026_02_01_b')], $appState['service']);

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(
            ['data_hotfix.2026_01_01_a' => '2026-04-30T12:00:00+00:00', 'data_hotfix.plugin_2026_02_01_b' => '2026-04-30T12:00:00+00:00'],
            $appState['written'],
        );
        static::assertStringContainsString('hotfixes: 2 recorded as done without running', $tester->getDisplay());
    }

    public function testAHotfixAlreadyRecordedKeepsItsDate(): void
    {
        // Arrange
        $appState = $this->makeAppStateSpy(initial: ['data_hotfix.2026_01_01_a' => '2026-01-02T08:00:00+00:00']);
        $tester = $this->tester([new ExplodingHotfix('2026_01_01_a')], $appState['service']);

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame([], $appState['written']);
        static::assertStringContainsString('hotfixes: 0 recorded as done without running', $tester->getDisplay());
    }

    /** @param list<DataHotfixInterface> $hotfixes */
    private function tester(array $hotfixes, AppStateService $appState): CommandTester
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-30T12:00:00+00:00'));

        return new CommandTester(new DataHotfixBaselineCommand(new Runner($hotfixes, $appState, $clock)));
    }

    /**
     * @param array<string,string> $initial
     * @return array{service: AppStateService, written: array<string,string>}
     */
    private function makeAppStateSpy(array $initial = []): array
    {
        $store = $initial;
        $written = [];

        $service = $this->createStub(AppStateService::class);
        $service->method('get')->willReturnCallback(static fn(string $key): ?string => $store[$key] ?? null);
        $service
            ->method('set')
            ->willReturnCallback(static function (string $key, string $value) use (&$store, &$written): void {
                $store[$key] = $value;
                $written[$key] = $value;
            });

        return ['service' => $service, 'written' => &$written];
    }
}

final readonly class ExplodingHotfix implements DataHotfixInterface
{
    public function __construct(
        private string $identifier,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function execute(): void
    {
        throw new RuntimeException('a baseline must not run a hotfix');
    }
}
