<?php declare(strict_types=1);

namespace Tests\Functional\Service\Security\Incident;

use App\Entity\AccessDeniedLog;
use App\Entity\Incident;
use App\Repository\AccessDeniedLogRepository;
use App\Repository\IncidentRepository;
use App\Service\AppStateService;
use App\Service\Security\Incident\IncidentMerger;
use App\Service\Security\Incident\IncidentSeverityCalculator;
use App\Service\Security\Incident\Sources\AccessDeniedIncidentSource;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

class AccessDeniedIncidentSourceTest extends KernelTestCase
{
    private const string NOW = '2026-05-07 12:00:00';
    private const string IP_A = '203.0.113.60';

    private EntityManagerInterface $em;
    private AccessDeniedLogRepository $repo;
    private IncidentRepository $incidentRepo;
    private AppStateService $appState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(AccessDeniedLogRepository::class);
        $this->incidentRepo = $container->get(IncidentRepository::class);
        $this->appState = $container->get(AppStateService::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    public function testFiveOrMoreHitsCreateIncident(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 6; $i++) {
            $this->seed(self::IP_A, '/admin/' . $i, $base->modify('+' . ($i * 60) . ' seconds'));
        }
        $this->em->flush();

        // Act
        $stats = $this->source()->ingest();

        // Assert
        self::assertSame(1, $stats->incidentsTouched);
        $incidents = $this->incidentRepo->getRecent(10);
        self::assertCount(1, $incidents);
        self::assertSame(6, $incidents[0]->getAccessDeniedHits());
        self::assertSame(6, $incidents[0]->getTotalHits());
    }

    public function testFewerThanThresholdDoesNotCreateIncident(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 4; $i++) {
            $this->seed(self::IP_A, '/admin/' . $i, $base->modify('+' . ($i * 60) . ' seconds'));
        }
        $this->em->flush();

        // Act
        $stats = $this->source()->ingest();

        // Assert
        self::assertSame(0, $stats->incidentsTouched);
        self::assertSame(0, $this->incidentRepo->countAll());
    }

    private function source(?string $now = null): AccessDeniedIncidentSource
    {
        $clock = new MockClock(new DateTimeImmutable($now ?? self::NOW));
        $merger = new IncidentMerger(
            $this->em,
            $this->incidentRepo,
            new IncidentSeverityCalculator(),
            $clock,
        );

        return new AccessDeniedIncidentSource(
            $this->em,
            $this->repo,
            $merger,
            $this->appState,
            $clock,
        );
    }

    private function seed(string $ip, string $url, DateTimeImmutable $createdAt): void
    {
        $row = new AccessDeniedLog();
        $row->setIp($ip);
        $row->setUrl($url);
        $row->setReason('access_denied');
        $row->setCreatedAt($createdAt);
        $this->em->persist($row);
    }

    private function purge(): void
    {
        $this->em->createQueryBuilder()->delete(Incident::class, 'i')->getQuery()->execute();
        $this->em->createQueryBuilder()
            ->delete(AccessDeniedLog::class, 'a')
            ->where('a.ip = :ip')
            ->setParameter('ip', self::IP_A)
            ->getQuery()->execute();
        $this->appState->remove(AccessDeniedIncidentSource::KEY_LAST_PROCESSED_ID);
        $this->em->clear();
    }
}
