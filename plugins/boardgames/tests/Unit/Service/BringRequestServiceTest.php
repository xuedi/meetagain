<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Enum\RequestStatus;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Service\BringRequestService;
use Plugin\Boardgames\Service\PledgeService;
use ReflectionProperty;
use RuntimeException;

class BringRequestServiceTest extends TestCase
{
    public function testAskingAnAskableOwnerOpensTheRequest(): void
    {
        // Arrange
        $service = $this->service($this->askableOwnership(), null);

        // Act
        $request = $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), 'please');

        // Assert
        static::assertSame(RequestStatus::Open, $request->getStatus());
        static::assertSame('please', $request->getMessage());
    }

    public function testAskingYourselfIsRefused(): void
    {
        // Arrange
        $service = $this->service($this->askableOwnership(), null);
        $requester = $this->user(7);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_cannot_ask_self');

        // Act
        $service->ask(new Event(), new Game(), $requester, $requester, null);
    }

    public function testAskingAnOwnerWhoIsNotWillingToBringIsRefused(): void
    {
        // Arrange
        $ownership = new GameOwnership()->setWillingToBring(false);
        $service = $this->service($ownership, null);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_owner_not_askable');

        // Act
        $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), null);
    }

    public function testAskingAnOwnerWhoKeptTheRowPrivateIsRefused(): void
    {
        // Arrange
        $ownership = new GameOwnership()->setPublic(false);
        $service = $this->service($ownership, null);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_owner_not_askable');

        // Act
        $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), null);
    }

    public function testAskingWhenNobodyOwnsTheTitleIsRefused(): void
    {
        // Arrange
        $service = $this->service(null, null);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_owner_not_askable');

        // Act
        $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), null);
    }

    public function testASecondRequestForTheSameTitleAtTheSameEventIsRefused(): void
    {
        // Arrange
        $existing = new BringRequest()->setStatus(RequestStatus::Open);
        $service = $this->service($this->askableOwnership(), $existing);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_request_already_open');

        // Act
        $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), null);
    }

    public function testADeclinedRequestCannotBeReopenedForTheSameEvent(): void
    {
        // Arrange
        $existing = new BringRequest()->setStatus(RequestStatus::Declined);
        $service = $this->service($this->askableOwnership(), $existing);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_request_declined_before');

        // Act
        $service->ask(new Event(), new Game(), $this->user(1), $this->user(2), null);
    }

    public function testAcceptingCreatesThePledgeAndClosesTheRequest(): void
    {
        // Arrange
        $owner = $this->user(2);
        $event = new Event();
        $event->addRsvp($owner);
        $game = new Game();
        $request = $this->openRequest($event, $game, $owner);

        $pledges = $this->createMock(PledgeService::class);
        $pledges->expects(self::once())->method('pledge')->with($event, $game, $owner);
        $service = $this->service($this->askableOwnership(), null, $pledges);

        // Act
        $service->accept($request);

        // Assert
        static::assertSame(RequestStatus::Accepted, $request->getStatus());
        static::assertNotNull($request->getRespondedAt());
    }

    public function testDecliningClosesTheRequestWithoutAPledge(): void
    {
        // Arrange
        $request = $this->openRequest(new Event(), new Game(), $this->user(2));
        $pledges = $this->createMock(PledgeService::class);
        $pledges->expects(self::never())->method('pledge');
        $service = $this->service($this->askableOwnership(), null, $pledges);

        // Act
        $service->decline($request);

        // Assert
        static::assertSame(RequestStatus::Declined, $request->getStatus());
    }

    public function testWithdrawingClosesTheRequest(): void
    {
        // Arrange
        $request = $this->openRequest(new Event(), new Game(), $this->user(2));
        $service = $this->service($this->askableOwnership(), null);

        // Act
        $service->withdraw($request);

        // Assert
        static::assertSame(RequestStatus::Withdrawn, $request->getStatus());
    }

    public function testExpiringClosesTheRequest(): void
    {
        // Arrange
        $request = $this->openRequest(new Event(), new Game(), $this->user(2));
        $service = $this->service($this->askableOwnership(), null);

        // Act
        $service->expire($request);

        // Assert
        static::assertSame(RequestStatus::Expired, $request->getStatus());
    }

    public function testAnAlreadyClosedRequestCannotBeAnsweredTwice(): void
    {
        // Arrange
        $request = $this->openRequest(new Event(), new Game(), $this->user(2))->setStatus(RequestStatus::Accepted);
        $service = $this->service($this->askableOwnership(), null);

        // Assert
        $this->expectExceptionMessage('boardgames_tile.flash_request_closed');

        // Act
        $service->decline($request);
    }

    public function testAcceptingARequestWhoseTargetsAreGoneIsRefused(): void
    {
        // Arrange
        $request = new BringRequest();
        $service = $this->service($this->askableOwnership(), null);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->accept($request);
    }

    private function openRequest(Event $event, Game $game, User $owner): BringRequest
    {
        return new BringRequest()
            ->setEvent($event)
            ->setGame($game)
            ->setRequestedBy($this->user(1))
            ->setOwnerUser($owner);
    }

    private function askableOwnership(): GameOwnership
    {
        return new GameOwnership();
    }

    private function service(?GameOwnership $ownership, ?BringRequest $existing, ?PledgeService $pledges = null): BringRequestService
    {
        $requestRepo = $this->createStub(BringRequestRepository::class);
        $requestRepo->method('findOneFor')->willReturn($existing);

        $ownershipRepo = $this->createStub(GameOwnershipRepository::class);
        $ownershipRepo->method('findOneFor')->willReturn($ownership);

        return new BringRequestService(
            $this->createStub(EntityManagerInterface::class),
            $requestRepo,
            $ownershipRepo,
            $pledges ?? $this->createStub(PledgeService::class),
        );
    }

    private function user(int $id): User
    {
        $user = new User();
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
