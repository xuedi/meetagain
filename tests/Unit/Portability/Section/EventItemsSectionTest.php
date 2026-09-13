<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\EventItemAssociation;
use App\Entity\User;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\EventItemsSection;
use App\Repository\UserRepository;
use DateTimeImmutable;

final class EventItemsSectionTest extends SectionTestCase
{
    public function testALinkSurvivesTheRoundTrip(): void
    {
        // Arrange
        $creator = $this->withId(new User(), 7);
        $creator->setEmail('cook@example.org');
        $exported = $this->section([$this->link()], [$creator])->export(
            new Scope(users: [7 => 'user'], eventIds: [4], itemIds: ['dish' => [12]]),
            $this->images(),
        );

        $context = $this->context();
        $event = new Event();
        $context->mapRef(Event::class, 4, $event);
        $context->mapItems('dish', [12 => 91]);
        $context->mapRef(User::class, 'cook@example.org', $this->withId(new User(), 30));

        // Act
        $this->section()->import($exported, $context);

        // Assert
        static::assertSame([[
            'event_ref' => 4,
            'item_type' => 'dish',
            'item_ref' => 12,
            'position' => 2,
            'section_label' => 'Mains',
            'created_by_email' => 'cook@example.org',
            'created_at' => '2030-01-06T19:00:00+00:00',
        ]], $exported);
        $link = $this->onlyPersisted(EventItemAssociation::class);
        static::assertSame($event, $link->getEvent());
        static::assertSame('dish', $link->getItemType());
        static::assertSame(91, $link->getItemId());
        static::assertSame(30, $link->getCreatedBy());
        static::assertSame(2, $link->getPosition());
        static::assertSame('Mains', $link->getSectionLabel());
        static::assertSame('2030-01-06 19:00', $link->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertSame(1, $context->toSummary()->get('event_items', Outcome::Created));
    }

    public function testALinkToAnItemOutsideTheScopeIsNotExported(): void
    {
        // Act
        $exported = $this->section([$this->link()])->export(new Scope(eventIds: [4], itemIds: ['dish' => [99]]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testALinkByAMemberWhoIsNotIncludedIsCreditedToTheSteward(): void
    {
        // Arrange
        $creator = $this->withId(new User(), 7);
        $creator->setEmail('cook@example.org');

        // Act
        $exported = $this->section([$this->link()], [$creator])->export(
            new Scope(eventIds: [4], itemIds: ['dish' => [12]], stewardEmail: 'steward@example.org'),
            $this->images(),
        );

        // Assert
        static::assertSame('steward@example.org', $exported[0]['created_by_email']);
    }

    public function testALinkToATypeThatWasNotImportedIsSkipped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 4, new Event());

        // Act
        $this->section()->import([['event_ref' => 4, 'item_type' => 'karaoke', 'item_ref' => 12]], $context);

        // Assert
        static::assertSame(['event_items' => ['skipped' => 1]], $context->toSummary()->counts);
        static::assertSame([], $this->persisted);
    }

    public function testALinkIsDroppedWithEitherSide(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 4, new Event());
        $context->mapItems('dish', [12 => 91]);

        // Act
        $this->section()->import([
            ['event_ref' => 5, 'item_type' => 'dish', 'item_ref' => 12],
            ['event_ref' => 4, 'item_type' => 'dish', 'item_ref' => 13],
        ], $context);

        // Assert
        static::assertSame(['event_items' => ['dropped' => 2]], $context->toSummary()->counts);
        static::assertSame([], $this->persisted);
    }

    public function testALinkWithoutAKnownCreatorIsCreditedToTheImportUser(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 4, new Event());
        $context->mapItems('dish', [12 => 91]);

        // Act
        $this->section()->import([['event_ref' => 4, 'item_type' => 'dish', 'item_ref' => 12, 'created_by_email' => null]], $context);

        // Assert
        static::assertSame((int) $context->getSystemUser()->getId(), $this->onlyPersisted(EventItemAssociation::class)->getCreatedBy());
    }

    /**
     * @param list<EventItemAssociation> $links
     * @param list<User> $users
     */
    private function section(array $links = [], array $users = []): EventItemsSection
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturn($users);

        return new EventItemsSection($this->entityManager($links), $userRepository);
    }

    private function link(): EventItemAssociation
    {
        return new EventItemAssociation()
            ->setEvent($this->withId(new Event(), 4))
            ->setItemType('dish')
            ->setItemId(12)
            ->setCreatedBy(7)
            ->setCreatedAt(new DateTimeImmutable('2030-01-06T19:00:00+00:00'))
            ->setPosition(2)
            ->setSectionLabel('Mains');
    }
}
