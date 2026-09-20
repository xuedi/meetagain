<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventInterval;
use App\Portability\Scope;
use App\Portability\Section\SeriesSection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;

final class SeriesSectionTest extends SectionTestCase
{
    public function testACustomSeriesSurvivesTheRoundTrip(): void
    {
        // Arrange
        $source = $this->withId(new EventSeries(), 3);
        $source->setName('First Sundays');
        $source->setRule(EventInterval::Custom);
        $source->setRuleSpec('FREQ=MONTHLY;BYDAY=1SU');
        $event = new Event();
        $event->setSeries($source);
        $exported = new SeriesSection($this->entityManager([$event]))->export(new Scope(eventIds: [1]), $this->images());
        $context = $this->context();

        // Act
        new SeriesSection($this->entityManager())->import($exported, $context);

        // Assert
        $series = $this->onlyPersisted(EventSeries::class);
        static::assertSame('First Sundays', $series->getName());
        static::assertSame(EventInterval::Custom, $series->getRule());
        static::assertSame('FREQ=MONTHLY;BYDAY=1SU', $series->getRuleSpec());
        static::assertSame($series, $context->resolveRef(EventSeries::class, 3));
    }

    public function testTheSeriesOfEveryScopeEventAreExportedIncludingRecurringOccurrences(): void
    {
        // Arrange
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => [1, 2]], ['id' => 'ASC'])
            ->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        // Act
        $rows = new SeriesSection($em)->export(new Scope(eventIds: [1, 2]), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?EventInterval, ?string}>
     */
    public static function degradingRuleProvider(): iterable
    {
        yield 'a preset rule keeps no spec' => [['rule' => 'Weekly', 'ruleSpec' => 'FREQ=MONTHLY;BYDAY=1SU'], EventInterval::Weekly, null];
        yield 'an unparseable custom spec is dropped' => [['rule' => 'Custom', 'ruleSpec' => 'FREQ=HOURLY;BYDAY=1SU'], EventInterval::Custom, null];
        yield 'a closed series has no rule' => [['rule' => null], null, null];
        yield 'an unknown rule name becomes a closed series' => [['rule' => 'NoSuchInterval'], null, null];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('degradingRuleProvider')]
    public function testARuleDegradesInsteadOfFailingTheArchive(array $row, ?EventInterval $rule, ?string $spec): void
    {
        // Arrange
        $section = new SeriesSection($this->entityManager());

        // Act
        $section->import([['ref' => 1, 'name' => 'Imported', ...$row]], $this->context());

        // Assert
        $series = $this->onlyPersisted(EventSeries::class);
        static::assertSame($rule, $series->getRule());
        static::assertSame($spec, $series->getRuleSpec());
    }
}
