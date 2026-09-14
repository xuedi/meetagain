<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventInterval;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
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
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = (string) ($row['name'] ?? '');
            $rule = array_find(EventInterval::cases(), static fn(EventInterval $case): bool => $case->name === ($row['rule'] ?? null));

            $series = new EventSeries();
            $series->setName($name !== '' ? $name : 'Imported series');
            $series->setRule($rule);
            $series->setRuleSpec($this->readRuleSpec($row, $rule));
            $series->setCreatedAt(new DateTimeImmutable());

            $this->em->persist($series);
            $context->mapRef(EventSeries::class, (int) ($row['ref'] ?? 0), $series);
            $context->count($this->getKey(), Outcome::Created);
        }
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
}
