<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Security\Provider\AbstractSecurityProvider;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class AbstractSecurityProviderTest extends TestCase
{
    public function testReadOnlyShortCircuitsBeforeProcessEvent(): void
    {
        // Arrange - provider with processEvent stub that throws if invoked
        $provider = new FakeSecurityProvider(new ArrayAdapter(), new NullLogger());
        $provider->processEventShouldFail = true;

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4', readOnly: true);

        // Assert
        static::assertSame(0, $report->threatLevel);
        static::assertSame('No issues', $report->summary);
    }

    public function testHandlesTypeFilterPreventsProcessing(): void
    {
        // Arrange - fake says it does not handle the type
        $provider = new FakeSecurityProvider(new ArrayAdapter(), new NullLogger());
        $provider->handles = false;
        $provider->processEventShouldFail = true;

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4');

        // Assert - returns baseline report without invoking processEvent
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testProcessEventRunsAndPersistsState(): void
    {
        // Arrange
        $cache = new ArrayAdapter();
        $provider = new FakeSecurityProvider($cache, new NullLogger());
        $provider->nextResult = [
            'state' => ['attempts' => 1],
            'threatLevel' => 50,
            'summary' => 'noted',
            'details' => ['x' => 'y'],
        ];

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        // Assert - report carries fresh values
        static::assertSame(50, $report->threatLevel);
        static::assertSame('noted', $report->summary);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertSame(['x' => 'y'], $report->details);
        static::assertSame(1, $provider->persistLogCalls);
    }

    public function testAlreadyBlockedStateSkipsReprocessing(): void
    {
        // Arrange - prime the cache with a Block recommendation
        $cache = new ArrayAdapter();
        $provider = new FakeSecurityProvider($cache, new NullLogger());
        $provider->nextResult = [
            'state' => ['attempts' => 1],
            'threatLevel' => 100,
            'summary' => 'blocked!',
            'details' => [],
        ];
        // First call: writes Block to state
        $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        // Now flip processEvent to a tripwire and call again
        $provider->processEventShouldFail = true;

        // Act - second observe must short-circuit
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/foo'), [], 'sess', '1.2.3.4');

        // Assert - replays cached state
        static::assertSame(100, $report->threatLevel);
        static::assertSame('blocked!', $report->summary);
    }

    public function testThreatLevelAtOrAbove100RecommendsBlock(): void
    {
        // Arrange
        $provider = new FakeSecurityProvider(new ArrayAdapter(), new NullLogger());
        $provider->nextResult = [
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
        $provider = new FakeSecurityProvider(new ArrayAdapter(), new NullLogger());
        $provider->scanLogsResult = [
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

    public function testLoadStateReturnsEmptyArrayOnCacheException(): void
    {
        // Arrange - cache pool that throws on getItem
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new RuntimeException('cache broken'));

        $provider = new FakeSecurityProvider($cache, new NullLogger());
        $provider->nextResult = [
            'state' => ['x' => 1],
            'threatLevel' => 10,
            'summary' => 'fresh',
            'details' => [],
        ];

        // Act - loadState swallows, processEvent runs anyway
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(10, $report->threatLevel);
        static::assertSame('fresh', $report->summary);
        static::assertSame([], $provider->lastLoadedState);
    }

    public function testClearAllStateRemovesIndexedKeys(): void
    {
        // Arrange - populate state for two keys
        $cache = new ArrayAdapter();
        $provider = new FakeSecurityProvider($cache, new NullLogger());
        $provider->nextResult = [
            'state' => [],
            'threatLevel' => 1,
            'summary' => 'noted',
            'details' => [],
        ];
        foreach (['sess-a', 'sess-b'] as $sess) {
            $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], $sess, '1.2.3.4');
        }

        // Sanity: state keys exist
        static::assertTrue($cache->getItem('security_provider_fake_sess-a')->isHit());

        // Act
        $provider->clearAllState();

        // Assert - both state entries plus the index are gone
        static::assertFalse($cache->getItem('security_provider_fake_sess-a')->isHit());
        static::assertFalse($cache->getItem('security_provider_fake_sess-b')->isHit());
        static::assertFalse($cache->getItem('security_provider_state_index_fake')->isHit());
    }

    public function testClearAllStateSwallowsCacheErrors(): void
    {
        // Arrange - cache pool that throws on deleteItem
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $cache->method('getItem')->willReturn($item);
        $cache->method('deleteItem')->willThrowException(new RuntimeException('cannot delete'));

        $provider = new FakeSecurityProvider($cache, new NullLogger());

        // Act / Assert - no exception escapes
        $provider->clearAllState();
        static::assertTrue(true);
    }

    public function testCacheKeySanitisesUnsafeStateKey(): void
    {
        // Arrange - state key with characters that should be sanitised to underscores
        $cache = new ArrayAdapter();
        $provider = new FakeSecurityProvider($cache, new NullLogger());
        $provider->nextResult = [
            'state' => [],
            'threatLevel' => 1,
            'summary' => '',
            'details' => [],
        ];

        // Act - use a session id with slashes and colons
        $provider->observe(SecurityEventType::NotFound, Request::create('/'), [], 'weird:session/key', '1.2.3.4');

        // Assert - sanitised key was written
        static::assertTrue($cache->getItem('security_provider_fake_weird_session_key')->isHit());
    }
}

/**
 * Concrete fake exposing the protected hooks so the abstract behaviour is exercisable.
 */
class FakeSecurityProvider extends AbstractSecurityProvider
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
        return $this->handles;
    }

    #[Override]
    protected function processEvent(SecurityEventType $type, Request $request, array $context, string $ip, array $state): array
    {
        if ($this->processEventShouldFail) {
            throw new RuntimeException('processEvent should not have been invoked');
        }
        $this->lastLoadedState = $state;

        return (
            $this->nextResult ?? [
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
        return $this->scanLogsResult;
    }

    #[Override]
    protected function persistLog(Request $request, array $context): void
    {
        $this->persistLogCalls++;
    }
}
