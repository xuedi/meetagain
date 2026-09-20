<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Contribution\Registry;
use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Repository\LocationRepository;
use App\Review\LocationChangeTarget;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocationChangeTargetTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function fieldProvider(): iterable
    {
        yield 'the name is written' => ['name', 'Cafe Nord'];
        yield 'the street is written' => ['street', 'Nordstrasse 9'];
        yield 'the city is written' => ['city', 'Hamburg'];
        yield 'the postcode is written' => ['postcode', '20095'];
        yield 'the description is written' => ['description', 'Upstairs room'];
    }

    #[DataProvider('fieldProvider')]
    public function testApplyWritesOneFieldOntoTheVenue(string $field, string $value): void
    {
        // Arrange
        $location = $this->location();
        $target = $this->makeTarget($location);

        // Act
        $target->apply(7, $field, $value);

        // Assert
        self::assertSame($value, $this->fieldValue($location, $field));
    }

    public function testApplyDispatchesTheUpdateAction(): void
    {
        // Arrange
        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(EntityAction::UpdateLocation, 7);
        $target = $this->makeTarget($this->location(), dispatcher: $dispatcher);

        // Act
        $target->apply(7, 'name', 'Cafe Nord');
    }

    public function testAnEmptyCoordinateIsStoredAsNull(): void
    {
        // Arrange
        $location = $this->location();
        $target = $this->makeTarget($location);

        // Act
        $target->apply(7, 'longitude', '');

        // Assert
        self::assertNull($location->getLongitude());
    }

    public function testADeletedVenueFailsValidationInsteadOfBeingWritten(): void
    {
        // Arrange
        $target = $this->makeTarget(null);

        // Act
        $error = $target->validate(7, 'name', 'Cafe Nord');

        // Assert
        self::assertSame('location.validator_gone', $error);
        self::assertNull($target->getTargetLabel(7));
        self::assertNull($target->getTargetUrl(7));
    }

    public function testAVenueOutsideTheVisibleScopeIsTreatedAsGone(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location(), accessible: false);

        // Act
        $error = $target->validate(7, 'name', 'Cafe Nord');

        // Assert
        self::assertSame('location.validator_gone', $error);
    }

    public function testARequiredFieldCannotBeEmptied(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location());

        // Act
        $error = $target->validate(7, 'name', '   ');

        // Assert
        self::assertSame('location.validator_blank', $error);
    }

    public function testAFieldThatIsNotPartOfAVenueIsRejected(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location());

        // Act
        $error = $target->validate(7, 'capacity', '30');

        // Assert
        self::assertSame('location.validator_unknown_field', $error);
    }

    public function testReviewNeedsTheVenueCreatePermission(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location(), granted: false);

        // Act & Assert
        self::assertFalse($target->canReview(new User(), 7));
    }

    public function testAMemberMayProposeOnAVenueTheMemberVenueRuleAllows(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location(), mayTouch: true);

        // Act & Assert
        self::assertTrue($target->canPropose(new User(), 7));
    }

    public function testAVenueTheMemberVenueRuleRejectsCannotBeProposedOn(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location(), mayTouch: false);

        // Act & Assert
        self::assertFalse($target->canPropose(new User(), 7));
    }

    public function testProposingNeedsAnAuthenticatedMember(): void
    {
        // Arrange
        $target = $this->makeTarget($this->location(), granted: false, mayTouch: true);

        // Act & Assert
        self::assertFalse($target->canPropose(new User(), 7));
    }

    private function makeTarget(
        ?Location $location,
        bool $accessible = true,
        bool $granted = true,
        ?EntityActionDispatcher $dispatcher = null,
        bool $mayTouch = true,
    ): LocationChangeTarget {
        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $filterService = $this->createStub(AdminLocationListFilterService::class);
        $filterService->method('isLocationAccessible')->willReturn($accessible);

        $contributions = $this->createStub(Registry::class);
        $contributions->method('mayTouch')->willReturn($mayTouch);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new LocationChangeTarget(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $filterService,
            $contributions,
            $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
            $security,
            $this->createStub(RouterInterface::class),
            $translator,
        );
    }

    private function location(): Location
    {
        $location = new Location();
        $location->setName('Cafe Central');
        $location->setDescription('Corner table');
        $location->setStreet('Hauptstrasse 1');
        $location->setCity('Berlin');
        $location->setPostcode('10115');
        $location->setLongitude('13.4');

        return $location;
    }

    private function fieldValue(Location $location, string $field): ?string
    {
        return match ($field) {
            'name' => $location->getName(),
            'description' => $location->getDescription(),
            'street' => $location->getStreet(),
            'city' => $location->getCity(),
            'postcode' => $location->getPostcode(),
            default => null,
        };
    }
}
