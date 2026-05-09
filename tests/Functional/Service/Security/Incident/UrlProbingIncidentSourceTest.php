<?php declare(strict_types=1);

namespace Tests\Functional\Service\Security\Incident;

use App\Entity\Incident;
use App\Entity\NotFoundLog;
use App\Repository\IncidentRepository;
use App\Repository\NotFoundLogRepository;
use App\Service\AppStateService;
use App\Service\Security\Incident\IncidentMerger;
use App\Service\Security\Incident\IncidentSeverityCalculator;
use App\Service\Security\Incident\Sources\UrlProbingIncidentSource;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

class UrlProbingIncidentSourceTest extends KernelTestCase
{
    private const string NOW = '2026-05-07 12:00:00';
    private const string IP_A = '203.0.113.50';

    private EntityManagerInterface $em;
    private NotFoundLogRepository $notFoundRepo;
    private IncidentRepository $incidentRepo;
    private AppStateService $appState;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->notFoundRepo = $container->get(NotFoundLogRepository::class);
        $this->incidentRepo = $container->get(IncidentRepository::class);
        $this->appState = $container->get(AppStateService::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    public function testEnoughProbesProducesIncidentWithProbingHits(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 35; $i++) {
            $this->seedRow(self::IP_A, '/.env-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $source = $this->source();

        // Act
        $stats = $source->ingest();

        // Assert
        self::assertSame(1, $stats->incidentsTouched);
        $incidents = $this->incidentRepo->getRecent(10);
        self::assertCount(1, $incidents);
        $incident = $incidents[0];
        self::assertSame(35, $incident->getProbingHits());
        self::assertSame(35, $incident->getTotalHits());
        self::assertSame(self::IP_A, $incident->getIp());
        self::assertSame(35, $incident->getDistinctPaths());
    }

    public function testTooFewProbesDoesNotProduceIncident(): void
    {
        // Arrange
        $base = new DateTimeImmutable('2026-05-07 09:00:00');
        for ($i = 0; $i < 10; $i++) {
            $this->seedRow(self::IP_A, '/p-' . $i, $base->modify('+' . ($i * 30) . ' seconds'));
        }
        $this->em->flush();
        $source = $this->source();

        // Act
        $stats = $source->ingest();

        // Assert
        self::assertSame(0, $stats->incidentsTouched);
        self::assertSame(0, $this->incidentRepo->countAll());
    }

    private function source(?string $now = null): UrlProbingIncidentSource
    {
        $clock = new MockClock(new DateTimeImmutable($now ?? self::NOW));
        $merger = new IncidentMerger(
            $this->em,
            $this->incidentRepo,
            new IncidentSeverityCalculator(),
            $clock,
        );

        return new UrlProbingIncidentSource(
            $this->em,
            $this->notFoundRepo,
            $this->incidentRepo,
            $merger,
            $this->appState,
            $clock,
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
        $this->em->createQueryBuilder()->delete(Incident::class, 'i')->getQuery()->execute();
        $this->em->createQueryBuilder()
            ->delete(NotFoundLog::class, 'n')
            ->where('n.ip = :ip')
            ->setParameter('ip', self::IP_A)
            ->getQuery()->execute();
        $this->appState->remove(UrlProbingIncidentSource::KEY_LAST_PROCESSED_ID);
        $this->em->clear();
    }
}
