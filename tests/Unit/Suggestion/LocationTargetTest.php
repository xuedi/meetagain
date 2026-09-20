<?php declare(strict_types=1);

namespace Tests\Unit\Suggestion;

use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Filter\Location\LocationFilterResult;
use App\Repository\LocationRepository;
use App\Suggestion\LocationTarget;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocationTargetTest extends TestCase
{
    public function testAPayloadRoundTripsThroughADraft(): void
    {
        // Arrange
        $target = $this->makeTarget();
        $payload = [
            'name' => 'Cafe Central',
            'description' => 'Corner table',
            'street' => 'Hauptstrasse 1',
            'city' => 'Berlin',
            'postcode' => '10115',
            'longitude' => '13.4',
            'latitude' => '52.5',
        ];

        // Act
        $draft = $target->fromPayload($payload);

        // Assert
        self::assertSame($payload, $target->toPayload($draft));
    }

    public function testAnEmptyCoordinateComesBackAsNullRatherThanAnEmptyString(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act
        $draft = $target->fromPayload(['name' => 'Cafe', 'postcode' => '10115', 'longitude' => '', 'latitude' => '']);

        // Assert
        self::assertNull($target->toPayload($draft)['longitude']);
        self::assertNull($target->toPayload($draft)['latitude']);
    }

    public function testADraftMatchingAVisibleVenueByNameAndPostcodeIsRejected(): void
    {
        // Arrange
        $target = $this->makeTarget([$this->location(7, 'Cafe Central', '10115')]);

        // Act
        $error = $target->validate($this->draft('cafe central', '10115'));

        // Assert
        self::assertSame('location.validator_duplicate', $error);
    }

    public function testTheSameNameInAnotherPostcodeIsNotADuplicate(): void
    {
        // Arrange
        $target = $this->makeTarget([$this->location(7, 'Cafe Central', '10115')]);

        // Act
        $error = $target->validate($this->draft('Cafe Central', '20095'));

        // Assert
        self::assertNull($error);
    }

    public function testADraftWithoutANameIsRejected(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act
        $error = $target->validate($this->draft('', '10115'));

        // Assert
        self::assertSame('location.validator_incomplete', $error);
    }

    public function testCreatePersistsTheVenueForTheProposerAndDispatchesTheCreateAction(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(EntityAction::CreateLocation, 0);
        $target = $this->makeTarget(dispatcher: $dispatcher, em: $em);
        $proposer = new User();
        $draft = $this->draft('Cafe Central', '10115');

        // Act
        $target->create($draft, $proposer);

        // Assert
        self::assertSame($proposer, $draft->getUser());
        self::assertNotNull($draft->getCreatedAt());
    }

    /** @param list<Location> $visible */
    private function makeTarget(array $visible = [], ?EntityActionDispatcher $dispatcher = null, ?EntityManagerInterface $em = null): LocationTarget
    {
        $repo = $this->createStub(LocationRepository::class);
        $repo->method('findAllForAdmin')->willReturn($visible);

        $filterService = $this->createStub(AdminLocationListFilterService::class);
        $filterService->method('getLocationIdFilter')->willReturn(LocationFilterResult::noFilter());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new LocationTarget(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repo,
            $filterService,
            $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
            $this->createStub(Security::class),
            $translator,
        );
    }

    private function draft(string $name, string $postcode): Location
    {
        $draft = new Location();
        $draft->setName($name);
        $draft->setDescription('');
        $draft->setStreet('Hauptstrasse 1');
        $draft->setCity('Berlin');
        $draft->setPostcode($postcode);

        return $draft;
    }

    private function location(int $id, string $name, string $postcode): Location
    {
        $location = $this->draft($name, $postcode);
        new ReflectionProperty(Location::class, 'id')->setValue($location, $id);

        return $location;
    }
}
