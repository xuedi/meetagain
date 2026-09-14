<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Ballot\Purpose;

use App\Entity\Event;
use App\Entity\Location;
use App\Event\BallotLocationChoice;
use App\Portability\Ballot\Purpose\VenuePurpose;
use App\Portability\Scope;
use Module\Ballot\Contract\BallotSubject;
use Tests\Unit\Portability\Section\SectionTestCase;

final class VenuePurposeTest extends SectionTestCase
{
    public function testItClaimsTheVenueBallotOnly(): void
    {
        // Arrange
        $purpose = new VenuePurpose();

        // Act
        $claims = [$purpose->supports(BallotLocationChoice::PURPOSE), $purpose->supports('event.item.film')];

        // Assert
        static::assertSame([true, false], $claims);
    }

    public function testABallotBelongsToTheScopeOfItsEvent(): void
    {
        // Arrange
        $purpose = new VenuePurpose();

        // Act
        $inScope = [
            $purpose->inScope(BallotLocationChoice::PURPOSE, new BallotSubject('event', 9), ['4'], new Scope(eventIds: [9])),
            $purpose->inScope(BallotLocationChoice::PURPOSE, new BallotSubject('event', 9), ['4'], new Scope(eventIds: [8])),
        ];

        // Assert
        static::assertSame([true, false], $inScope);
    }

    public function testVenuesAreRekeyedAndTheUndecidedChoiceStays(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(Location::class, 4, $this->withId(new Location(), 40));
        $purpose = new VenuePurpose();

        // Act
        $subject = $purpose->importSubject(new BallotSubject('event', 9), $context);
        $keys = [
            $purpose->importKey(BallotLocationChoice::PURPOSE, '4', $context),
            $purpose->importKey(BallotLocationChoice::PURPOSE, '5', $context),
            $purpose->importKey(BallotLocationChoice::PURPOSE, BallotLocationChoice::CANDIDATE_UNDECIDED, $context),
        ];

        // Assert
        static::assertEquals(new BallotSubject('event', 90), $subject);
        static::assertSame(['40', null, BallotLocationChoice::CANDIDATE_UNDECIDED], $keys);
    }
}
