<?php declare(strict_types=1);

namespace Tests\Unit\Contribution;

use App\Contribution\LocationSection;
use App\Contribution\ScopeFilterInterface;
use App\Contribution\ScopeFilterService;
use App\Entity\Location;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocationSectionTest extends TestCase
{
    #[DataProvider('provideReachability')]
    public function testMayTouchIsTheUnionOfEventVisibilityAndScope(bool $exists, bool $carriedByVisibleEvent, bool $inScope, bool $expected): void
    {
        // Arrange
        $section = $this->section($exists, $carriedByVisibleEvent, $inScope);

        // Act
        $verdict = $section->mayTouch(new User(), 7);

        // Assert
        self::assertSame($expected, $verdict);
    }

    /**
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function provideReachability(): iterable
    {
        yield 'a venue that does not exist is never touchable' => [false, true, true, false];
        yield 'a venue carried by an event the member can see' => [true, true, false, true];
        yield 'a venue offered in the member own listing' => [true, false, true, true];
        yield 'reachable by both routes' => [true, true, true, true];
        yield 'reachable by neither route' => [true, false, false, false];
    }

    public function testTheListingCarriesTheVenueNameAndItsAddress(): void
    {
        // Arrange
        $venue = new Location();
        $venue->setName('Cafe Central');
        $venue->setStreet('Hauptstrasse 1');
        $venue->setCity('Berlin');

        $locationRepo = $this->createStub(LocationRepository::class);
        $locationRepo->method('findAllForAdmin')->willReturn([$venue]);
        $section = new LocationSection(
            $locationRepo,
            $this->createStub(EventRepository::class),
            $this->createStub(EventFilterService::class),
            new ScopeFilterService([]),
        );

        // Act
        $entries = $section->listForMember(new User());

        // Assert
        self::assertCount(1, $entries);
        self::assertSame('Cafe Central', $entries[0]->label);
        self::assertSame('Hauptstrasse 1, Berlin', $entries[0]->sublabel);
    }

    public function testAVenueWithNoAddressHasNoSublabel(): void
    {
        // Arrange
        $venue = new Location();
        $venue->setName('Somewhere');

        $locationRepo = $this->createStub(LocationRepository::class);
        $locationRepo->method('findAllForAdmin')->willReturn([$venue]);
        $section = new LocationSection(
            $locationRepo,
            $this->createStub(EventRepository::class),
            $this->createStub(EventFilterService::class),
            new ScopeFilterService([]),
        );

        // Act
        $entries = $section->listForMember(new User());

        // Assert
        self::assertNull($entries[0]->sublabel);
    }

    private function section(bool $exists, bool $carriedByVisibleEvent, bool $inScope): LocationSection
    {
        $locationRepo = $this->createStub(LocationRepository::class);
        $locationRepo->method('find')->willReturn($exists ? new Location() : null);

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findIdsByLocation')->willReturn([1]);

        $eventFilter = $this->createStub(EventFilterService::class);
        $eventFilter->method('getAccessibleEventIds')->willReturn($carriedByVisibleEvent ? [1] : []);

        return new LocationSection($locationRepo, $eventRepo, $eventFilter, new ScopeFilterService([$this->filter($inScope)]));
    }

    private function filter(bool $allows): ScopeFilterInterface
    {
        return new class($allows) implements ScopeFilterInterface {
            public function __construct(
                private readonly bool $allows,
            ) {}

            public function getPriority(): int
            {
                return 0;
            }

            public function narrowContributableIds(string $type, array $ids, User $user): ?array
            {
                return $this->allows ? $ids : [];
            }
        };
    }
}
