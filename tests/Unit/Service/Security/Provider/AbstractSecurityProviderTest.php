<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Cache\AtomicStoreInterface;
use App\Service\Security\Provider\AbstractSecurityProvider;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Tests\Unit\Service\Cache\InMemoryAtomicStore;

class AbstractSecurityProviderTest extends TestCase
{
    public function testReadOnlyShortCircuitsBeforeProcessEvent(): void
    {
        // Arrange
        $provider = new FakeSecurityProvider(new InMemoryAtomicStore(), new NullLogger());
        $provider->control->processEventShouldFail = true;

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4', readOnly: true);

        // Assert
        static::assertSame(0, $report->threatLevel);
        static::assertSame('No issues', $report->summary);
    }

    public function testHandlesTypeFilterPreventsProcessing(): void
    {
        // Arrange
        $provider = new FakeSecurityProvider(new InMemoryAtomicStore(), new NullLogger());
        $provider->control->handles = false;
        $provider->control->processEventShouldFail = true;

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testProcessEventRunsAndPersistsState(): void
    {
        // Arrange
        $store = new InMemoryAtomicStore();
        $provider = new FakeSecurityProvider($store, new NullLogger());
        $provider->control->nextResult = [
            'state' => ['attempts' => 1],
            'threatLevel' => 50,
            'summary' => 'noted',
            'details' => ['x' => 'y'],
        ];

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(50, $report->threatLevel);
        static::assertSame('noted', $report->summary);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertSame(['x' => 'y'], $report->details);
        static::assertSame(1, $provider->control->persistLogCalls);
    }

    public function testAlreadyBlockedStateSkipsReprocessing(): void
    {
        // Arrange
        $store = new InMemoryAtomicStore();
        $provider = new FakeSecurityProvider($store, new NullLogger());
        $provider->control->nextResult = [
            'state' => ['attempts' => 1],
            'threatLevel' => 100,
            'summary' => 'blocked!',
            'details' => [],
        ];
        $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        $provider->control->processEventShouldFail = true;

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(100, $report->threatLevel);
        static::assertSame('blocked!', $report->summary);
    }

    public function testThreatLevelAtOrAbove100RecommendsBlock(): void
    {
        // Arrange
        $provider = new FakeSecurityProvider(new InMemoryAtomicStore(), new NullLogger());
        $provider->control->nextResult = [
            'state' => [],
            'threatLevel' => 150,
            'summary' => 'too much',
            'details' => [],
        ];

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testScanRetrospectiveBuildsReportFromScanLogs(): void
    {
        // Arrange
        $provider = new FakeSecurityProvider(new InMemoryAtomicStore(), new NullLogger());
        $provider->control->scanLogsResult = [
            'threatLevel' => 7,
            'summary' => 'historical scan',
            'details' => ['hits' => 3],
        ];

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-12'));

        // Assert
        static::assertSame(7, $report->threatLevel);
        static::assertSame('historical scan', $report->summary);
        static::assertSame(['hits' => 3], $report->details);
    }

    public function testABrokenStoreStillReturnsAFreshReport(): void
    {
        // Arrange
        $store = $this->createStub(AtomicStoreInterface::class);
        $store->method('update')->willThrowException(new RuntimeException('valkey broken'));

        $provider = new FakeSecurityProvider($store, new NullLogger());
        $provider->control->nextResult = [
            'state' => ['x' => 1],
            'threatLevel' => 10,
            'summary' => 'fresh',
            'details' => [],
        ];

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(10, $report->threatLevel);
        static::assertSame('fresh', $report->summary);
        static::assertSame([], $provider->control->lastLoadedState);
    }

    public function testClearAllStateRemovesIndexedKeys(): void
    {
        // Arrange
        $store = new InMemoryAtomicStore();
        $provider = new FakeSecurityProvider($store, new NullLogger());
        $provider->control->nextResult = [
            'state' => [],
            'threatLevel' => 1,
            'summary' => 'noted',
            'details' => [],
        ];
        foreach (['sess-a', 'sess-b'] as $sess) {
            $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], $sess, '1.2.3.4');
        }

        static::assertNotNull($store->read('security:state:fake:sess-a'));

        // Act
        $provider->clearAllState();

        // Assert
        static::assertNull($store->read('security:state:fake:sess-a'));
        static::assertNull($store->read('security:state:fake:sess-b'));
        static::assertSame([], $store->indexEntries('security:index:fake'));
    }

    public function testClearAllStateSwallowsStoreErrors(): void
    {
        // Arrange
        $store = $this->createStub(AtomicStoreInterface::class);
        $store->method('indexEntries')->willReturn([]);
        $store->method('delete')->willThrowException(new RuntimeException('cannot delete'));

        $provider = new FakeSecurityProvider($store, new NullLogger());

        // Act / Assert
        $provider->clearAllState();
        static::assertTrue(true);
    }

    public function testStoreKeySanitisesUnsafeStateKey(): void
    {
        // Arrange
        $store = new InMemoryAtomicStore();
        $provider = new FakeSecurityProvider($store, new NullLogger());
        $provider->control->nextResult = [
            'state' => [],
            'threatLevel' => 1,
            'summary' => '',
            'details' => [],
        ];

        // Act
        $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'weird:session/key', '1.2.3.4');

        // Assert
        static::assertNotNull($store->read('security:state:fake:weird_session_key'));
    }
}

final readonly class FakeSecurityProvider extends AbstractSecurityProvider
{
    public function __construct(
        AtomicStoreInterface $store,
        LoggerInterface $logger,
        public FakeSecurityProviderControl $control = new FakeSecurityProviderControl(),
    ) {
        parent::__construct($store, $logger);
    }

    public function getKey(): string
    {
        return 'fake';
    }

    public function getPriority(): int
    {
        return 0;
    }

    protected function handlesType(SecurityEventType $type): bool
    {
        return $this->control->handles;
    }

    #[Override]
    protected function processEvent(SecurityEventType $type, Request $request, array $context, string $ip, array $state): array
    {
        if ($this->control->processEventShouldFail) {
            throw new RuntimeException('processEvent should not have been invoked');
        }
        $this->control->lastLoadedState = $state;

        return (
            $this->control->nextResult ?? [
                'state' => [],
                'threatLevel' => 0,
                'summary' => 'noop',
                'details' => [],
            ]
        );
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->control->scanLogsResult;
    }

    #[Override]
    protected function persistLog(Request $request, array $context): void
    {
        $this->control->persistLogCalls++;
    }
}

class FakeSecurityProviderControl
{
    public bool $handles = true;
    public bool $processEventShouldFail = false;
    public int $persistLogCalls = 0;
    /** @var array<string, mixed> */
    public array $lastLoadedState = [];
    /** @var array{state: array<string, mixed>, threatLevel: int, summary: string, details: array<string, mixed>}|null */
    public ?array $nextResult = null;
    /** @var array{threatLevel: int, summary: string, details: array<string, mixed>} */
    public array $scanLogsResult = ['threatLevel' => 0, 'summary' => '', 'details' => []];
}
