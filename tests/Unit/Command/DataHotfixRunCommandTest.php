<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\DataHotfixRunCommand;
use App\DataHotfix\DataHotfixInterface;
use App\DataHotfix\DeferredException;
use App\Service\AppStateService;
use ArrayObject;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

class DataHotfixRunCommandTest extends TestCase
{
    public function testFreshHotfixRunsAndLocks(): void
    {
        // Arrange
        $log = new ArrayObject();
        $hotfix = new RecordingHotfix('a_fresh', $log);
        $appState = $this->makeAppStateSpy();

        $tester = $this->tester([$hotfix], $appState['service'], $this->makeClock('2026-04-30T12:00:00+00:00'), $this->createStub(LoggerInterface::class));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(['a_fresh'], $log->getArrayCopy());
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('hotfixes: 1 ran, 0 skipped, 0 deferred, 0 failed', $tester->getDisplay());
        static::assertSame('2026-04-30T12:00:00+00:00', $appState['written']['data_hotfix.a_fresh'] ?? null);
    }

    public function testAlreadyRunHotfixIsSkipped(): void
    {
        // Arrange
        $log = new ArrayObject();
        $hotfix = new RecordingHotfix('b_done', $log);
        $appState = $this->makeAppStateSpy(initial: ['data_hotfix.b_done' => '2026-04-29T08:00:00+00:00']);

        $tester = $this->tester([$hotfix], $appState['service'], $this->makeClock('2026-04-30T12:00:00+00:00'), $this->createStub(LoggerInterface::class));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame([], $log->getArrayCopy(), 'execute() must not be called when AppState lock is set');
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('hotfixes: 0 ran, 1 skipped, 0 deferred, 0 failed', $tester->getDisplay());
    }

    public function testFailingHotfixDoesNotLock(): void
    {
        // Arrange
        $hotfix = new RecordingHotfix('c_throws', new ArrayObject(), new RuntimeException('boom'));
        $appState = $this->makeAppStateSpy();
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                'Data hotfix failed',
                static::callback(static fn(array $context) => $context['identifier'] === 'c_throws' && isset($context['exception'])),
            );

        $tester = $this->tester([$hotfix], $appState['service'], $this->makeClock('2026-04-30T12:00:00+00:00'), $logger);

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('hotfixes: 0 ran, 0 skipped, 0 deferred, 1 failed', $tester->getDisplay());
        static::assertArrayNotHasKey('data_hotfix.c_throws', $appState['written']);
    }

    public function testHotfixesRunInIdentifierOrder(): void
    {
        // Arrange
        $log = new ArrayObject();
        $c = new RecordingHotfix('z_third', $log);
        $a = new RecordingHotfix('a_first', $log);
        $b = new RecordingHotfix('m_second', $log);

        $tester = $this->tester(
            [$c, $a, $b],
            $this->makeAppStateSpy()['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame(['a_first', 'm_second', 'z_third'], $log->getArrayCopy());
    }

    public function testMixedRunSkipFailAggregates(): void
    {
        // Arrange
        $ok = new RecordingHotfix('a_ok', new ArrayObject());
        $skip = new RecordingHotfix('b_skip', new ArrayObject());
        $fail = new RecordingHotfix('c_fail', new ArrayObject(), new RuntimeException('boom'));

        $appState = $this->makeAppStateSpy(initial: ['data_hotfix.b_skip' => '2026-04-29T08:00:00+00:00']);

        $tester = $this->tester(
            [$ok, $skip, $fail],
            $appState['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('hotfixes: 1 ran, 1 skipped, 0 deferred, 1 failed', $tester->getDisplay());
    }

    public function testAHotfixThatDefersRunsAgainOnceTheOthersHaveRun(): void
    {
        // Arrange
        $log = new ArrayObject();
        $deferring = new DeferOnceHotfix('a_defers', $log);
        $other = new RecordingHotfix('b_other', $log);

        $tester = $this->tester(
            [$deferring, $other],
            $this->makeAppStateSpy()['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(['a_defers', 'b_other', 'a_defers'], $log->getArrayCopy());
        static::assertStringContainsString('hotfixes: 2 ran, 0 skipped, 0 deferred, 0 failed', $tester->getDisplay());
    }

    public function testAFailureIsPrintedWithItsReason(): void
    {
        // Arrange
        $hotfix = new RecordingHotfix('c_throws', new ArrayObject(), new RuntimeException('boom'));

        $tester = $this->tester(
            [$hotfix],
            $this->makeAppStateSpy()['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $tester->execute([]);

        // Assert
        static::assertStringContainsString('data hotfix failed: c_throws: boom', $tester->getDisplay());
    }

    public function testAHotfixWaitingForStateIsDeferredWithoutFailingTheRun(): void
    {
        // Arrange
        $hotfix = new RecordingHotfix('d_waits', new ArrayObject(), new DeferredException('no main group yet'));
        $appState = $this->makeAppStateSpy();

        $tester = $this->tester([$hotfix], $appState['service'], $this->makeClock('2026-04-30T12:00:00+00:00'), $this->createStub(LoggerInterface::class));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('data hotfix deferred: d_waits: no main group yet', $tester->getDisplay());
        static::assertStringContainsString('hotfixes: 0 ran, 0 skipped, 1 deferred, 0 failed', $tester->getDisplay());
        static::assertArrayNotHasKey('data_hotfix.d_waits', $appState['written']);
    }

    private function tester(iterable $hotfixes, AppStateService $appState, ClockInterface $clock, LoggerInterface $logger): CommandTester
    {
        return new CommandTester(new DataHotfixRunCommand($hotfixes, $appState, $clock, $logger));
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

    private function makeClock(string $iso): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable($iso));

        return $clock;
    }
}

final class RecordingHotfix implements DataHotfixInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly ArrayObject $log,
        private readonly ?Throwable $throws = null,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function execute(): void
    {
        $this->log->append($this->identifier);
        if ($this->throws !== null) {
            throw $this->throws;
        }
    }
}

final class DeferOnceHotfix implements DataHotfixInterface
{
    private bool $deferred = false;

    public function __construct(
        private readonly string $identifier,
        private readonly ArrayObject $log,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function execute(): void
    {
        $this->log->append($this->identifier);
        if (!$this->deferred) {
            $this->deferred = true;

            throw new DeferredException('deferring');
        }
    }
}
