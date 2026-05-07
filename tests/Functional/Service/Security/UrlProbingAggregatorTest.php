<?php declare(strict_types=1);

namespace Tests\Functional\Service\Security;

use App\Entity\NotFoundLog;
use App\Entity\UrlProbingIncident;
use App\Repository\NotFoundLogRepository;
use App\Repository\UrlProbingIncidentRepository;
use App\Service\AppStateService;
use App\Service\Security\UrlProbingAggregator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

class UrlProbingAggregatorTest extends KernelTestCase
{
    private const string NOW = '2026-05-07 12:00:00';
    private const string IP_A = '203.0.113.50';
    private const string IP_B = '203.0.113.51';

    private EntityManagerInterface $em;
    private NotFoundLogRepository $notFoundRepo;
    private UrlProbingIncidentRepository $incidentRepo;
    private AppStateService $appState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->notFoundRepo = $container->get(NotFoundLogRepository::class);
        $this->incidentRepo = $container->get(UrlProbingIncidentRepository::class);
        $this->appState = $container->get(AppStateService::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    public function testEnoughProbesProducesIncident(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/.env-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(1, $stats['incidents']);
        $incidents = $this->incidentRepo->getRecent(10);
        self::assertCount(1, $incidents);
        $incident = $incidents[0];
        self::assertSame(35, $incident->getProbeCount());
        self::assertSame(35, $incident->getDistinctUrlCount());
        self::assertSame(self::IP_A, $incident->getIp());
        self::assertCount(10, $incident->getSampleUrls());
    }

    public function testTooFewProbesDoesNotProduceIncident(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 10; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(0, $stats['incidents']);
        self::assertSame(0, $this->incidentRepo->countAll());
        self::assertGreaterThan(0, $this->em->getRepository(NotFoundLog::class)->count(['ip' => self::IP_A]));
    }

    public function testRowsInsideSettleWindowAreIgnored(): void
    {
        // Arrange: 35 rows, all within 30 minutes of "now"
        $base = new DateTimeImmutable('2026-05-07 11:35:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/recent-' . $i, $base->modify('+' . ($i * 5) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(0, $stats['incidents']);
        self::assertSame(0, $this->incidentRepo->countAll());
    }

    public function testTwoSeriesSeparatedByLargeGapProduceTwoIncidents(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 06:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/a-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $second = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/b-' . $i, $second->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(2, $stats['incidents']);
        self::assertSame(2, $this->incidentRepo->countAll());
    }

    public function testSecondRunIsIdempotent(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act
        $aggregator->aggregate();
        $secondStats = $aggregator->aggregate();

        // Assert
        self::assertSame(0, $secondStats['incidents']);
        self::assertSame(1, $this->incidentRepo->countAll());
    }

    public function testWatermarkIsPersistedAndResumesAcrossRuns(): void
    {
        // Arrange: settled series for IP_A
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();

        // Act 1: drains backlog and stores watermark
        $first = $aggregator->aggregate();
        self::assertSame(1, $first['incidents']);
        self::assertGreaterThan(0, $first['lastProcessedId']);
        self::assertSame((string) $first['lastProcessedId'], $this->appState->get(UrlProbingAggregator::KEY_LAST_PROCESSED_ID));

        // Arrange: new settled series for IP_B added later
        $second = new DateTimeImmutable('2026-05-07 10:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_B, '/q-' . $i, $second->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();

        // Act 2: should resume past the stored watermark, only see IP_B rows
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(1, $stats['incidents']);
        self::assertSame(1, $stats['ipsProcessed']);
        self::assertSame(2, $this->incidentRepo->countAll());
    }

    public function testTailSeriesIsDeferredAndPickedUpWhenContinuationIsGone(): void
    {
        // Arrange: 35 settled rows for IP_A within the batch, plus one continuation
        // row at 11:10 (past cutoff at 11:00, within 30 min of the last in-batch row).
        $base = new DateTimeImmutable('2026-05-07 10:38:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->seedRow(self::IP_A, '/p-live', new DateTimeImmutable('2026-05-07 11:10:00'));
        $this->em->flush();
        $firstSeriesMinId = (int) $this->em->createQueryBuilder()
            ->select('MIN(n.id)')
            ->from(NotFoundLog::class, 'n')
            ->where('n.url LIKE :prefix')
            ->setParameter('prefix', '/p-%')
            ->andWhere('n.url <> :live')
            ->setParameter('live', '/p-live')
            ->getQuery()->getSingleScalarResult();

        // Act 1: defers the only series
        $first = $this->aggregator()->aggregate();
        self::assertSame(0, $first['incidents']);
        self::assertSame(0, $this->incidentRepo->countAll());
        // Watermark must stay below the first row of the deferred series so the rows
        // are re-fetched on the next run.
        $watermarkAfterDefer = (int) ($this->appState->get(UrlProbingAggregator::KEY_LAST_PROCESSED_ID) ?? '0');
        self::assertLessThan($firstSeriesMinId, $watermarkAfterDefer);

        // Arrange: continuation goes quiet (drop the live row, advance the clock)
        $this->em->createQueryBuilder()
            ->delete(NotFoundLog::class, 'n')
            ->where('n.url = :url')
            ->setParameter('url', '/p-live')
            ->getQuery()->execute();
        $this->em->clear();

        // Act 2: clock advances enough for the prior tail to settle
        $stats = $this->aggregator('2026-05-07 13:00:00')->aggregate();

        // Assert
        self::assertSame(1, $stats['incidents']);
        self::assertSame(1, $this->incidentRepo->countAll());
    }

    public function testPruningRawRowsAfterIncidentDoesNotProduceDuplicates(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $aggregator = $this->aggregator();
        $aggregator->aggregate();
        self::assertSame(1, $this->incidentRepo->countAll());

        $this->em->createQueryBuilder()
            ->delete(NotFoundLog::class, 'n')
            ->where('n.ip = :ip')
            ->setParameter('ip', self::IP_A)
            ->getQuery()->execute();
        $this->em->clear();

        // Act
        $stats = $aggregator->aggregate();

        // Assert
        self::assertSame(0, $stats['incidents']);
        self::assertSame(1, $this->incidentRepo->countAll());
    }

    private function aggregator(?string $now = null): UrlProbingAggregator
    {
        return new UrlProbingAggregator(
            $this->em,
            $this->notFoundRepo,
            $this->incidentRepo,
            $this->appState,
            new MockClock(new DateTimeImmutable($now ?? self::NOW)),
            new NullLogger(),
        );
    }

    private function seedRow(string $ip, string $url, DateTimeImmutable $createdAt): void
    {
        $row = new NotFoundLog();
        $row->setIp($ip);
        $row->setUrl($url);
        $row->setCreatedAt($createdAt);
        $this->em->persist($row);
    }

    private function purge(): void
    {
        $this->em->createQueryBuilder()->delete(UrlProbingIncident::class, 'i')->getQuery()->execute();
        $this->em->createQueryBuilder()
            ->delete(NotFoundLog::class, 'n')
            ->where('n.ip IN (:ips)')
            ->setParameter('ips', [self::IP_A, self::IP_B])
            ->getQuery()->execute();
        $this->appState->remove(UrlProbingAggregator::KEY_LAST_PROCESSED_ID);
        $this->em->clear();
    }
}
