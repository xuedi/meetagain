<?php declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Entity\Location;
use App\Event\BallotLocationChoice;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Filter\Location\LocationFilterResult;
use App\Repository\LocationRepository;
use Module\Ballot\Contract\BallotInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class BallotLocationChoiceTest extends TestCase
{
    public function testASingleVenueStillOffersTheEntry(): void
    {
        // Arrange
        $provider = $this->provider([new Location()]);

        // Act & Assert
        self::assertTrue($provider->isAvailableFor(null));
    }

    public function testWithoutAnyVenueThereIsNothingToDecide(): void
    {
        // Arrange
        $provider = $this->provider([]);

        // Act & Assert
        self::assertFalse($provider->isAvailableFor(null));
    }

    public function testTheEntryIsCoreAndThereforeNeverGatedByAPlugin(): void
    {
        // Arrange
        $provider = $this->provider([new Location()]);

        // Act & Assert
        self::assertSame('', $provider->getPluginKey());
    }

    /**
     * @param list<Location> $venues
     */
    private function provider(array $venues): BallotLocationChoice
    {
        $repository = $this->createStub(LocationRepository::class);
        $repository->method('findAllForAdmin')->willReturn($venues);

        $filter = $this->createStub(AdminLocationListFilterService::class);
        $filter->method('getLocationIdFilter')->willReturn(LocationFilterResult::noFilter());

        return new BallotLocationChoice(
            $this->createStub(BallotInterface::class),
            $repository,
            $filter,
            $this->createStub(Security::class),
            $this->createStub(TranslatorInterface::class),
        );
    }
}
