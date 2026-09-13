<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Ballot\Purpose;

use App\Entity\Event;
use App\Portability\Ballot\Purpose\ItemPurpose;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Tests\Unit\Portability\Section\SectionTestCase;

final class ItemPurposeTest extends SectionTestCase
{
    public function testItClaimsTheItemBallotsOfEveryType(): void
    {
        // Arrange
        $purpose = new ItemPurpose();

        // Act
        $claims = [$purpose->supports('event.item.film'), $purpose->supports('event.item.dish'), $purpose->supports('event.location')];

        // Assert
        static::assertSame([true, true, false], $claims);
    }

    public function testABallotBelongsToTheScopeOfItsEvent(): void
    {
        // Arrange
        $purpose = new ItemPurpose();
        $scope = new Scope(eventIds: [9]);

        // Act
        $inScope = [
            $purpose->inScope('event.item.film', new BallotSubject('event', 9), ['12'], $scope),
            $purpose->inScope('event.item.film', new BallotSubject('event', 8), ['12'], $scope),
            $purpose->inScope('event.item.film', null, ['12'], $scope),
        ];

        // Assert
        static::assertSame([true, false, false], $inScope);
    }

    public function testTheEventAndTheItemsAreRekeyed(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapItems('film', [12 => 120]);
        $purpose = new ItemPurpose();

        // Act
        $subject = $purpose->importSubject(new BallotSubject('event', 9), $context);
        $keys = [$purpose->importKey('event.item.film', '12', $context), $purpose->importKey('event.item.film', '13', $context)];

        // Assert
        static::assertEquals(new BallotSubject('event', 90), $subject);
        static::assertSame(['120', null], $keys);
    }

    public function testAnEventTheArchiveDoesNotCarryResolvesToNothing(): void
    {
        // Act
        $subject = new ItemPurpose()->importSubject(new BallotSubject('event', 9), $this->context());

        // Assert
        static::assertNull($subject);
    }
}
