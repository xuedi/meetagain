<?php declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Activity\ActivityService;
use App\EntityActionDispatcher;
use App\Enum\EventType;
use App\Event\Proposal;
use App\Event\SuggestionTarget;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class SuggestionTargetTest extends TestCase
{
    public function testAPayloadRoundTripsThroughADraft(): void
    {
        // Arrange
        $target = $this->makeTarget();
        $payload = [
            'start' => '2030-05-01T19:00:00+00:00',
            'stop' => '2030-05-01T22:00:00+00:00',
            'type' => EventType::Dinner->value,
            'location' => '12',
            'locale' => 'de',
            'title' => 'Dumpling night',
            'teaser' => 'We fold together',
            'description' => 'Bring an apron.',
        ];

        // Act
        $draft = $target->fromPayload($payload);

        // Assert
        self::assertSame($payload, $target->toPayload($draft));
    }

    public function testAnEmptyPayloadGivesADraftWithoutDatesTypeOrVenue(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act
        $draft = $target->fromPayload([]);

        // Assert
        self::assertInstanceOf(Proposal::class, $draft);
        self::assertNull($draft->start);
        self::assertNull($draft->stop);
        self::assertNull($draft->type);
        self::assertNull($draft->location);
    }

    public function testANewDraftIsWrittenInTheRequestLocale(): void
    {
        // Arrange
        $request = new Request();
        $request->setLocale('fr');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $target = $this->makeTarget($requestStack);

        // Act
        $draft = $target->newDraft();

        // Assert
        self::assertInstanceOf(Proposal::class, $draft);
        self::assertSame('fr', $draft->locale);
    }

    #[DataProvider('provideInvalidDrafts')]
    public function testAnInvalidDraftIsRejected(Proposal $draft, string $expected): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act
        $error = $target->validate($draft);

        // Assert
        self::assertSame($expected, $error);
    }

    public static function provideInvalidDrafts(): iterable
    {
        yield 'no title' => [self::draft(title: ''), 'event_proposal.validator_incomplete'];
        yield 'no description' => [self::draft(description: ' '), 'event_proposal.validator_incomplete'];
        yield 'no start' => [self::draft(start: null), 'event_proposal.validator_incomplete'];
        yield 'start in the past' => [self::draft(start: new DateTimeImmutable('-1 day')), 'event_proposal.validator_start_past'];
        yield 'stop before start' => [self::draft(stop: new DateTimeImmutable('+1 day')), 'event_proposal.validator_stop_before_start'];
    }

    public function testACompleteFutureDraftIsValid(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act
        $error = $target->validate(self::draft());

        // Assert
        self::assertNull($error);
    }

    private static function draft(
        string $title = 'Dumpling night',
        string $description = 'Bring an apron.',
        ?DateTimeImmutable $start = new DateTimeImmutable('+2 days'),
        ?DateTimeImmutable $stop = null,
    ): Proposal {
        $draft = new Proposal();
        $draft->title = $title;
        $draft->description = $description;
        $draft->start = $start;
        $draft->stop = $stop;

        return $draft;
    }

    private function makeTarget(?RequestStack $requestStack = null): SuggestionTarget
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SuggestionTarget(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EntityActionDispatcher::class),
            $this->createStub(ActivityService::class),
            $this->createStub(Security::class),
            $requestStack ?? new RequestStack(),
            $translator,
        );
    }
}
