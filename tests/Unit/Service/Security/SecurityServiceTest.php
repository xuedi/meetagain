<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Entity\AccessDeniedLog;
use App\Entity\Incident;
use App\Entity\NotFoundLog;
use App\Enum\CronTaskStatus;
use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\AccessDeniedLogRepository;
use App\Repository\NotFoundLogRepository;
use App\Service\AppStateService;
use App\Service\Security\BlockedSessionStore;
use App\Service\Security\ProviderReport;
use App\Service\Security\RequestIdentityResolver;
use App\Service\Security\SecurityProviderInterface;
use App\Service\Security\SecurityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;

class SecurityServiceTest extends TestCase
{
    public function testEventNoOpsWhenIpIsBlocked(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $blockStore->blockIp('1.2.3.4', ['reason' => 'fuse']);

        $provider = $this->createMock(SecurityProviderInterface::class);
        $provider->expects($this->never())->method('observe');

        $service = $this->buildService([$provider], $blockStore);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert - mock expectations verify
        static::assertTrue(true);
    }

    public function testEventNoOpsWhenSessionIsBlocked(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $blockStore->blockSession('ip:1.2.3.4', ['reason' => 'provider']);

        $provider = $this->createMock(SecurityProviderInterface::class);
        $provider->expects($this->never())->method('observe');

        $service = $this->buildService([$provider], $blockStore);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert
        static::assertTrue(true);
    }

    public function testProvidersAreCalledInPriorityDescOrder(): void
    {
        // Arrange
        $callOrder = [];
        $low = $this->makeProvider('low', priority: 0, recommendation: SecurityRecommendation::Handled, callOrder: $callOrder);
        $high = $this->makeProvider('high', priority: 1000, recommendation: SecurityRecommendation::Handled, callOrder: $callOrder);

        $service = $this->buildService([$low, $high]);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert
        static::assertSame(['high', 'low'], $callOrder);
    }

    public function testShortCircuitPutsRestInReadOnlyAndSkipsIncident(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        $fuse = $this->makeProvider('fuse', priority: 1000, recommendation: SecurityRecommendation::BlockShortCircuit);
        $detector = $this->createMock(SecurityProviderInterface::class);
        $detector->method('getKey')->willReturn('not_found');
        $detector->method('getPriority')->willReturn(0);
        $detector->method('handles')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('observe')
            ->with(static::anything(), static::anything(), static::anything(), static::anything(), static::anything(), true)
            ->willReturn(new ProviderReport('not_found', 0, 'cached', SecurityRecommendation::Handled));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $service = $this->buildService([$fuse, $detector], $blockStore, em: $em);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert - the IP is blocked, no incident written
        static::assertTrue($blockStore->isIpBlocked('1.2.3.4'));
    }

    public function testDetectionBlockWritesIncidentAndBlocksBoth(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        $detector = $this->makeProvider('not_found', priority: 0, recommendation: SecurityRecommendation::Block, threatLevel: 95);

        $persistedIncident = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedIncident): void {
                $persistedIncident = $entity;
            });
        $em->expects($this->exactly(2))->method('flush');

        $notFoundLog = new NotFoundLog();
        $notFoundRepo = $this->createStub(NotFoundLogRepository::class);
        $notFoundRepo->method('findLatestUnlinkedForOffender')->willReturn($notFoundLog);

        $service = $this->buildService([$detector], $blockStore, em: $em, notFoundLogRepository: $notFoundRepo);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert
        static::assertInstanceOf(Incident::class, $persistedIncident);
        static::assertSame('not_found', $persistedIncident->getTriggeredBy());
        static::assertTrue($blockStore->isIpBlocked('1.2.3.4'));
        static::assertTrue($blockStore->isSessionBlocked('ip:1.2.3.4'));
        static::assertSame($persistedIncident, $notFoundLog->getIncident());
    }

    public function testNotFoundBlockStampsLatestNotFoundLogRow(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $detector = $this->makeProvider('not_found', priority: 0, recommendation: SecurityRecommendation::Block, threatLevel: 80);

        $logRow = new NotFoundLog();
        $notFoundRepo = $this->createMock(NotFoundLogRepository::class);
        $notFoundRepo->expects($this->once())->method('findLatestUnlinkedForOffender')->with('1.2.3.4', 'ip:1.2.3.4')->willReturn($logRow);

        $accessDeniedRepo = $this->createStub(AccessDeniedLogRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $service = $this->buildService([$detector], $blockStore, em: $em, notFoundLogRepository: $notFoundRepo, accessDeniedLogRepository: $accessDeniedRepo);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::NotFound, $request);

        // Assert
        static::assertInstanceOf(Incident::class, $logRow->getIncident());
    }

    public function testAccessDeniedBlockStampsLatestAccessDeniedLogRow(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $detector = $this->makeProvider('access_denied', priority: 0, recommendation: SecurityRecommendation::Block, threatLevel: 80);

        $logRow = new AccessDeniedLog();
        $accessDeniedRepo = $this->createMock(AccessDeniedLogRepository::class);
        $accessDeniedRepo->expects($this->once())->method('findLatestUnlinkedForOffender')->with('1.2.3.4')->willReturn($logRow);

        $notFoundRepo = $this->createStub(NotFoundLogRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $service = $this->buildService([$detector], $blockStore, em: $em, notFoundLogRepository: $notFoundRepo, accessDeniedLogRepository: $accessDeniedRepo);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::AccessDenied, $request);

        // Assert
        static::assertInstanceOf(Incident::class, $logRow->getIncident());
    }

    public function testUnrecognisedTriggeredByDoesNotStampAnyLogRow(): void
    {
        // Arrange
        $blockStore = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $detector = $this->makeProvider('rate_limit', priority: 0, recommendation: SecurityRecommendation::Block, threatLevel: 80);

        $notFoundRepo = $this->createMock(NotFoundLogRepository::class);
        $notFoundRepo->expects($this->never())->method('findLatestUnlinkedForOffender');

        $accessDeniedRepo = $this->createMock(AccessDeniedLogRepository::class);
        $accessDeniedRepo->expects($this->never())->method('findLatestUnlinkedForOffender');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->buildService([$detector], $blockStore, em: $em, notFoundLogRepository: $notFoundRepo, accessDeniedLogRepository: $accessDeniedRepo);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '1.2.3.4']);

        // Act
        $service->event(SecurityEventType::RateLimit, $request);

        // Assert - no stamping repos were called
        static::assertTrue($blockStore->isIpBlocked('1.2.3.4'));
    }

    public function testRetrospectiveScanUpdatesAppState(): void
    {
        // Arrange
        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->once())->method('set')->with(SecurityService::KEY_LAST_RETROSPECTIVE_RUN, static::anything());

        $detector = $this->createStub(SecurityProviderInterface::class);
        $detector->method('getKey')->willReturn('not_found');
        $detector->method('getPriority')->willReturn(0);
        $detector->method('scanRetrospective')->willReturn(new ProviderReport('not_found', 5, 'ok', SecurityRecommendation::Handled));

        $service = $this->buildService([$detector], appState: $appState);

        // Act
        $result = $service->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
    }

    /**
     * @param list<SecurityProviderInterface> $providers
     */
    private function buildService(
        array $providers,
        ?BlockedSessionStore $blockStore = null,
        ?CacheItemPoolInterface $cache = null,
        ?EntityManagerInterface $em = null,
        ?AppStateService $appState = null,
        ?NotFoundLogRepository $notFoundLogRepository = null,
        ?AccessDeniedLogRepository $accessDeniedLogRepository = null,
    ): SecurityService {
        $blockStore ??= new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $em ??= $this->createStub(EntityManagerInterface::class);
        $appState ??= $this->createStub(AppStateService::class);
        $notFoundLogRepository ??= $this->createStub(NotFoundLogRepository::class);
        $accessDeniedLogRepository ??= $this->createStub(AccessDeniedLogRepository::class);

        return new SecurityService(
            providers: $providers,
            blockStore: $blockStore,
            em: $em,
            appState: $appState,
            clock: new MockClock(new DateTimeImmutable('2026-05-09 12:00:00')),
            logger: new NullLogger(),
            environment: 'test',
            notFoundLogRepository: $notFoundLogRepository,
            accessDeniedLogRepository: $accessDeniedLogRepository,
            identityResolver: new RequestIdentityResolver(new NullLogger()),
        );
    }

    /**
     * @param array<int, string> $callOrder
     */
    private function makeProvider(
        string $key,
        int $priority,
        SecurityRecommendation $recommendation,
        int $threatLevel = 0,
        ?array &$callOrder = null,
    ): SecurityProviderInterface {
        $report = new ProviderReport($key, $threatLevel, $key . ' summary', $recommendation);
        $provider = $this->createStub(SecurityProviderInterface::class);
        $provider->method('getKey')->willReturn($key);
        $provider->method('getPriority')->willReturn($priority);
        $provider->method('handles')->willReturn(true);
        $provider
            ->method('observe')
            ->willReturnCallback(static function () use ($key, $report, &$callOrder): ProviderReport {
                if ($callOrder !== null) {
                    $callOrder[] = $key;
                }
                return $report;
            });

        return $provider;
    }
}
