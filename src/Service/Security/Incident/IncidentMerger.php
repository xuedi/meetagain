<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use App\Entity\Incident;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Owns the upsert into logs_incident.
 *
 * 24h rolling window: a new contribution from an IP within 24h of that IP's
 * latest open Incident's ended_at extends the existing row. Outside that
 * window a new row is created. URL probing's 60-min settle and 30-min gap
 * govern *series detection inside that source*; this merger only sees the
 * already-detected series and treats it like any other contribution.
 */
final readonly class IncidentMerger
{
    public const int WINDOW_MINUTES = 24 * 60;
    public const int MAX_SAMPLE_URLS = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private IncidentRepository $incidentRepo,
        private IncidentSeverityCalculator $severityCalculator,
        private ClockInterface $clock,
    ) {}

    public function merge(IncidentSourceContribution $contribution): Incident
    {
        $minEndedAt = $contribution->endedAt->modify('-' . self::WINDOW_MINUTES . ' minutes');
        $existing = $this->incidentRepo->findOpenWindowForIp($contribution->ip, $minEndedAt);

        if ($existing !== null) {
            $this->applyContribution($existing, $contribution, isNew: false);

            return $existing;
        }

        $incident = new Incident();
        $incident->setIp($contribution->ip);
        $incident->setStartedAt($contribution->startedAt);
        $incident->setEndedAt($contribution->endedAt);
        $incident->setSampleUrls([]);
        $incident->setCreatedAt($this->clock->now());
        $incident->setUpdatedAt($this->clock->now());

        $this->applyContribution($incident, $contribution, isNew: true);
        $this->em->persist($incident);

        return $incident;
    }

    private function applyContribution(Incident $incident, IncidentSourceContribution $c, bool $isNew): void
    {
        match ($c->sourceKey) {
            IncidentSourceContribution::KEY_PROBING       => $incident->setProbingHits($incident->getProbingHits() + $c->hits),
            IncidentSourceContribution::KEY_ACCESS_DENIED => $incident->setAccessDeniedHits($incident->getAccessDeniedHits() + $c->hits),
            IncidentSourceContribution::KEY_RATE_LIMIT    => $incident->setRateLimitHits($incident->getRateLimitHits() + $c->hits),
            IncidentSourceContribution::KEY_BRUTE_FORCE   => $incident->setBruteForceHits($incident->getBruteForceHits() + $c->hits),
            default                                       => throw new \InvalidArgumentException('Unknown source key: ' . $c->sourceKey),
        };

        if ($c->startedAt < $incident->getStartedAt()) {
            $incident->setStartedAt($c->startedAt);
        }
        if ($c->endedAt > $incident->getEndedAt()) {
            $incident->setEndedAt($c->endedAt);
        }

        $existingSamples = $incident->getSampleUrls();
        $merged = array_values(array_unique(array_merge($existingSamples, $c->samplePaths)));
        $incident->setSampleUrls(array_slice($merged, 0, self::MAX_SAMPLE_URLS));

        $incident->setDistinctPaths($incident->getDistinctPaths() + $c->distinctPaths);
        $incident->setDistinctUserAgents($incident->getDistinctUserAgents() + $c->distinctUserAgents);

        $incident->setTotalHits(
            $incident->getProbingHits()
            + $incident->getAccessDeniedHits()
            + $incident->getRateLimitHits()
            + $incident->getBruteForceHits(),
        );

        if ($c->userAgentCounts !== [] && ($isNew || $incident->getUserAgent() === null)) {
            $counts = $c->userAgentCounts;
            arsort($counts);
            $incident->setUserAgent(array_key_first($counts));
        }

        $incident->setSeverity($this->severityCalculator->calculate(
            $incident->getProbingHits(),
            $incident->getAccessDeniedHits(),
            $incident->getRateLimitHits(),
            $incident->getBruteForceHits(),
        ));

        if (!$isNew) {
            $incident->setUpdatedAt($this->clock->now());
        }
    }
}
