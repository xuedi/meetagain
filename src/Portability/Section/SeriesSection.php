<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventInterval;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PurgeContext;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\ValueObject\RecurrencePattern;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class SeriesSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'series';
    }

    #[Override]
    public function getOrder(): int
    {
        return 30;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds], ['id' => 'ASC']) as $event) {
            $series = $event->getSeries();
            if (!$series instanceof EventSeries) {
                continue;
            }

            $seriesId = (int) $series->getId();
            if (isset($rows[$seriesId])) {
                continue;
            }

            $rows[$seriesId] = [
                'ref' => $seriesId,
                'name' => $series->getName(),
                'rule' => $series->getRule()?->name,
                'ruleSpec' => $series->getRuleSpec(),
            ];
        }

        return array_values($rows);
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $firstStarts = $this->firstStarts($context);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ref = (int) ($row['ref'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $existing = isset($firstStarts[$ref]) ? $this->findSeries($name, $firstStarts[$ref]) : null;
            if ($existing instanceof EventSeries) {
                $context->mapRef(EventSeries::class, $ref, $existing);
                $context->count($this->getKey(), Outcome::Matched);
                continue;
            }

            $rule = array_find(EventInterval::cases(), static fn(EventInterval $case): bool => $case->name === ($row['rule'] ?? null));

            $series = new EventSeries();
            $series->setName($name !== '' ? $name : 'Imported series');
            $series->setRule($rule);
            $series->setRuleSpec($this->readRuleSpec($row, $rule));
            $series->setCreatedAt(new DateTimeImmutable());

            $this->em->persist($series);
            $context->mapRef(EventSeries::class, $ref, $series);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @return array<int, DateTime> series ref => start of its first occurrence in the archive
     */
    private function firstStarts(ImportContext $context): array
    {
        $firstStarts = [];
        foreach ($context->archiveRows('events') as $event) {
            $seriesRef = $event['series_ref'] ?? null;
            $start = (string) ($event['start'] ?? '');
            if (!is_int($seriesRef) || $start === '') {
                continue;
            }

            $start = new DateTime($start);
            if (!isset($firstStarts[$seriesRef]) || $start < $firstStarts[$seriesRef]) {
                $firstStarts[$seriesRef] = $start;
            }
        }

        return $firstStarts;
    }

    private function findSeries(string $name, DateTime $firstStart): ?EventSeries
    {
        $series = $this->em
            ->createQuery(sprintf(
                'SELECT s FROM %s s WHERE s.name = :name AND (SELECT MIN(e.start) FROM %s e WHERE e.series = s) = :start',
                EventSeries::class,
                Event::class,
            ))
            ->setParameter('name', $name)
            ->setParameter('start', $firstStart, Types::DATETIME_MUTABLE)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $series instanceof EventSeries ? $series : null;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function readRuleSpec(array $row, ?EventInterval $rule): ?string
    {
        if (EventInterval::Custom !== $rule) {
            return null;
        }

        $spec = trim((string) ($row['ruleSpec'] ?? ''));
        if ('' === $spec) {
            return null;
        }

        try {
            return RecurrencePattern::fromRfcString($spec)->toRfcString();
        } catch (InvalidRecurrencePatternException) {
            return null;
        }
    }

    #[Override]
    public function purge(PurgeContext $context): void
    {
        $this->em->createQuery('DELETE App\Entity\EventSeries e')->execute();
    }
}
