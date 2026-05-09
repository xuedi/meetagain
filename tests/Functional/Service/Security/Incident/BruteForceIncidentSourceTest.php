<?php declare(strict_types=1);

namespace Tests\Functional\Service\Security\Incident;

use App\Entity\Incident;
use App\Entity\RateLimitLog;
use App\Repository\IncidentRepository;
use App\Repository\RateLimitLogRepository;
use App\Service\AppStateService;
use App\Service\Security\Incident\IncidentMerger;
use App\Service\Security\Incident\IncidentSeverityCalculator;
use App\Service\Security\Incident\Sources\BruteForceIncidentSource;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

class BruteForceIncidentSourceTest extends KernelTestCase
{
    private const string NOW = '2026-05-07 12:00:00';
    private const string IP_A = '203.0.113.80';

    private EntityManagerInterface $em;
    private RateLimitLogRepository $repo;
    private IncidentRepository $incidentRepo;
    private AppStateService $appState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(RateLimitLogRepository::class);
        $this->incidentRepo = $container->get(IncidentRepository::class);
        $this->appState = $container->get(AppStateService::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    public function testSingleLoginThrottlingHitCreatesIncident(): void
    {
        // Arrange
        $this->seed(self::IP_A, BruteForceIncidentSource::LOGIN_LIMITER, '/login', new DateTimeImmutable('2026-05-07 09:00:00'));
        $this->em->flush();

        // Act
        $stats = $this->source()->ingest();

        // Assert
        self::assertSame(1, $stats->incidentsTouched);
        $incidents = $this->incidentRepo->getRecent(10);
        self::assertSame(1, $incidents[0]->getBruteForceHits());
    }

    public function testNonLoginLimiterRowsAreExcluded(): void
    {
        // Arrange
        $this->seed(self::IP_A, 'comment_post', '/post', new DateTimeImmutable('2026-05-07 09:00:00'));
        $this->em->flush();

        // Act
        $stats = $this->source()->ingest();

        // Assert
        self::assertSame(0, $stats->incidentsTouched);
        self::assertSame(0, $this->incidentRepo->countAll());
    }

    private function source(?string $now = null): BruteForceIncidentSource
    {
        $clock = new MockClock(new DateTimeImmutable($now ?? self::NOW));
        $merger = new IncidentMerger(
            $this->em,
            $this->incidentRepo,
            new IncidentSeverityCalculator(),
            $clock,
        );

        return new BruteForceIncidentSource(
            $this->em,
            $this->repo,
            $merger,
            $this->appState,
            $clock,
        );
    }

    private function seed(string $ip, string $limiter, string $url, DateTimeImmutable $createdAt): void
    {
        $row = new RateLimitLog();
        $row->setIp($ip);
        $row->setLimiter($limiter);
        $row->setUrl($url);
        $row->setCreatedAt($createdAt);
        $this->em->persist($row);
    }

    private function purge(): void
    {
        $this->em->createQueryBuilder()->delete(Incident::class, 'i')->getQuery()->execute();
        $this->em->createQueryBuilder()
            ->delete(RateLimitLog::class, 'r')
            ->where('r.ip = :ip')
            ->setParameter('ip', self::IP_A)
            ->getQuery()->execute();
        $this->appState->remove(BruteForceIncidentSource::KEY_LAST_PROCESSED_ID);
        $this->em->clear();
    }
}
