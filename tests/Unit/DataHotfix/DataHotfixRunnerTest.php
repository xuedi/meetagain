<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\DataHotfixInterface;
use App\DataHotfix\DataHotfixRunner;
use App\Enum\CronTaskStatus;
use App\Service\AppStateService;
use ArrayObject;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\NullOutput;
use Throwable;

class DataHotfixRunnerTest extends TestCase
{
    public function testFreshHotfixRunsAndLocks(): void
    {
        // Arrange
        $log = new ArrayObject();
        $hotfix = new RecordingHotfix('a_fresh', $log);
        $appState = $this->makeAppStateSpy();

        $runner = new DataHotfixRunner(
            [$hotfix],
            $appState['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $result = $runner->runCronTask(new NullOutput());

        // Assert
        static::assertSame(['a_fresh'], $log->getArrayCopy());
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('hotfixes: 1 ran, 0 skipped, 0 failed', $result->message);
        static::assertSame('2026-04-30T12:00:00+00:00', $appState['written']['data_hotfix.a_fresh'] ?? null);
    }

    public function testAlreadyRunHotfixIsSkipped(): void
    {
        // Arrange
        $log = new ArrayObject();
        $hotfix = new RecordingHotfix('b_done', $log);
        $appState = $this->makeAppStateSpy(initial: ['data_hotfix.b_done' => '2026-04-29T08:00:00+00:00']);

        $runner = new DataHotfixRunner(
            [$hotfix],
            $appState['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $result = $runner->runCronTask(new NullOutput());

        // Assert
        static::assertSame([], $log->getArrayCopy(), 'execute() must not be called when AppState lock is set');
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('hotfixes: 0 ran, 1 skipped, 0 failed', $result->message);
    }

    public function testFailingHotfixDoesNotLock(): void
    {
        // Arrange
        $hotfix = new RecordingHotfix('c_throws', new ArrayObject(), new RuntimeException('boom'));
        $appState = $this->makeAppStateSpy();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error')
            ->with('Data hotfix failed', static::callback(
                static fn(array $context) => $context['identifier'] === 'c_throws' && isset($context['exception']),
            ));

        $runner = new DataHotfixRunner(
            [$hotfix],
            $appState['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $logger,
        );

        // Act
        $result = $runner->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
        static::assertSame('hotfixes: 0 ran, 0 skipped, 1 failed', $result->message);
        static::assertArrayNotHasKey('data_hotfix.c_throws', $appState['written']);
    }

    public function testHotfixesRunInIdentifierOrder(): void
    {
        // Arrange
        $log = new ArrayObject();
        $c = new RecordingHotfix('z_third', $log);
        $a = new RecordingHotfix('a_first', $log);
        $b = new RecordingHotfix('m_second', $log);

        $runner = new DataHotfixRunner(
            [$c, $a, $b],
            $this->makeAppStateSpy()['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $runner->runCronTask(new NullOutput());

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

        $runner = new DataHotfixRunner(
            [$ok, $skip, $fail],
            $appState['service'],
            $this->makeClock('2026-04-30T12:00:00+00:00'),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $result = $runner->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
        static::assertSame('hotfixes: 1 ran, 1 skipped, 1 failed', $result->message);
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
        $service->method('set')->willReturnCallback(static function (string $key, string $value) use (&$store, &$written): void {
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
