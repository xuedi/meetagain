<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Enum\PledgeStatus;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Repository\GamePledgeRepository;
use Plugin\Boardgames\Service\PledgeService;
use RuntimeException;

class PledgeServiceTest extends TestCase
{
    public function testPledgingWithoutAnRsvpIsRefused(): void
    {
        // Arrange
        $event = new Event();
        $service = $this->service(
            $this->createStub(GamePledgeRepository::class),
            $this->ownershipRepo(new GameOwnership()),
        );

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_rsvp_required');

        // Act
        $service->pledge($event, new Game(), new User());
    }

    public function testPledgingAGameNotOnTheOwnShelfIsRefused(): void
    {
        // Arrange
        $user = new User();
        $event = new Event();
        $event->addRsvp($user);
        $service = $this->service($this->createStub(GamePledgeRepository::class), $this->ownershipRepo(null));

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_ownership_required');

        // Act
        $service->pledge($event, new Game(), $user);
    }

    public function testPledgingCreatesAPledgedRow(): void
    {
        // Arrange
        $user = new User();
        $event = new Event();
        $event->addRsvp($user);
        $game = new Game();
        $pledgeRepo = $this->createStub(GamePledgeRepository::class);
        $pledgeRepo->method('findOneFor')->willReturn(null);
        $service = $this->service($pledgeRepo, $this->ownershipRepo(new GameOwnership()));

        // Act
        $pledge = $service->pledge($event, $game, $user);

        // Assert
        static::assertSame(PledgeStatus::Pledged, $pledge->getStatus());
        static::assertSame($game, $pledge->getGame());
        static::assertSame($user, $pledge->getUser());
    }

    public function testWithdrawingFlipsTheStatusWithoutDeletingTheRow(): void
    {
        // Arrange
        $user = new User();
        $event = new Event();
        $event->addRsvp($user);
        $pledgeRepo = $this->createStub(GamePledgeRepository::class);
        $pledgeRepo->method('findOneFor')->willReturn(null);
        $service = $this->service($pledgeRepo, $this->ownershipRepo(new GameOwnership()));
        $pledge = $service->pledge($event, new Game(), $user);

        // Act
        $service->withdraw($pledge);

        // Assert
        static::assertSame(PledgeStatus::Withdrawn, $pledge->getStatus());
    }

    public function testRepledgingReusesTheExistingRow(): void
    {
        // Arrange
        $user = new User();
        $event = new Event();
        $event->addRsvp($user);
        $game = new Game();
        $pledgeRepo = $this->createStub(GamePledgeRepository::class);
        $pledgeRepo->method('findOneFor')->willReturn(null);
        $service = $this->service($pledgeRepo, $this->ownershipRepo(new GameOwnership()));
        $first = $service->pledge($event, $game, $user);
        $service->withdraw($first);

        $repledgeRepo = $this->createStub(GamePledgeRepository::class);
        $repledgeRepo->method('findOneFor')->willReturn($first);
        $repledgeService = $this->service($repledgeRepo, $this->ownershipRepo(new GameOwnership()));

        // Act
        $second = $repledgeService->pledge($event, $game, $user);

        // Assert
        static::assertSame($first, $second);
        static::assertSame(PledgeStatus::Pledged, $second->getStatus());
    }

    private function service(GamePledgeRepository $pledges, GameOwnershipRepository $ownerships): PledgeService
    {
        return new PledgeService($this->createStub(EntityManagerInterface::class), $pledges, $ownerships);
    }

    private function ownershipRepo(?GameOwnership $ownership): GameOwnershipRepository
    {
        $repo = $this->createStub(GameOwnershipRepository::class);
        $repo->method('findOneFor')->willReturn($ownership);

        return $repo;
    }

    public function testRuntimeExceptionCarriesATranslationKey(): void
    {
        // Arrange
        $service = $this->service($this->createStub(GamePledgeRepository::class), $this->ownershipRepo(null));

        // Act
        try {
            $service->pledge(new Event(), new Game(), new User());
            $thrown = null;
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        // Assert
        static::assertNotNull($thrown);
        static::assertStringStartsWith('boardgames_tile.', $thrown->getMessage());
    }
}
