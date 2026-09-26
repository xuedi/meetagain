<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit;

use App\Activity\ActivityService;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\HandoverService;
use Module\Circulation\Internal\LedgerService;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class HandoverServiceTest extends TestCase
{
    /** @var list<LedgerEntry> */
    private array $ledgerRows = [];

    public function testOneSideConfirmingDoesNotMoveTheCopy(): void
    {
        // Arrange
        [$service, $handover, $giver] = $this->openHandover();

        // Act
        $service->confirm($handover, $giver);

        // Assert
        self::assertNotNull($handover->getFromConfirmedAt());
        self::assertNull($handover->getToConfirmedAt());
        self::assertSame(HandoverStatus::Open, $handover->getStatus());
        self::assertSame(CopyStatus::InHandover, $handover->getCopy()->getStatus());
    }

    public function testConfirmingTwiceFromTheSameSideChangesNothingFurther(): void
    {
        // Arrange
        [$service, $handover, $giver] = $this->openHandover();
        $service->confirm($handover, $giver);
        $firstStamp = $handover->getFromConfirmedAt();
        $rowsAfterFirst = count($this->ledgerRows);

        // Act
        $service->confirm($handover, $giver);

        // Assert
        self::assertSame($firstStamp, $handover->getFromConfirmedAt());
        self::assertCount($rowsAfterFirst, $this->ledgerRows);
        self::assertSame(HandoverStatus::Open, $handover->getStatus());
    }

    public function testBothSidesConfirmingMovesTheCopyAndFulfilsTheRequest(): void
    {
        // Arrange
        [$service, $handover, $giver, $receiver, $request] = $this->openHandover();

        // Act
        $service->confirm($handover, $giver);
        $service->confirm($handover, $receiver);

        // Assert
        self::assertSame(HandoverStatus::Completed, $handover->getStatus());
        self::assertSame($receiver, $handover->getCopy()->getHolder());
        self::assertSame(CopyStatus::Held, $handover->getCopy()->getStatus());
        self::assertNull($handover->getCopy()->getFinishedAt());
        self::assertSame(RequestStatus::Fulfilled, $request->getStatus());
        self::assertCount(1, $this->rowsOfType(LedgerEntryType::HandoverCompleted));
    }

    public function testAThirdPartyCannotConfirmForEitherSide(): void
    {
        // Arrange
        [$service, $handover] = $this->openHandover();
        $stranger = $this->user(99);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $service->confirm($handover, $stranger);
    }

    public function testAHandoverWithNoGiverCompletesOnTheReceiverAlone(): void
    {
        // Arrange
        [$service, $handover, , $receiver] = $this->openHandover(withGiver: false);

        // Act
        $service->confirm($handover, $receiver);

        // Assert
        self::assertSame(HandoverStatus::Completed, $handover->getStatus());
        self::assertSame($receiver, $handover->getCopy()->getHolder());
    }

    public function testCancellingReturnsTheCopyToTheGiverAndTheRequesterToTheQueue(): void
    {
        // Arrange
        [$service, $handover, $giver, , $request] = $this->openHandover();

        // Act
        $service->cancel($handover, $giver);

        // Assert
        self::assertSame(HandoverStatus::Cancelled, $handover->getStatus());
        self::assertSame($giver, $handover->getCopy()->getHolder());
        self::assertSame(CopyStatus::Available, $handover->getCopy()->getStatus());
        self::assertSame(RequestStatus::Waiting, $request->getStatus());
        self::assertNull($request->getOfferedCopy());
    }

    public function testConfirmingAClosedHandoverIsRefused(): void
    {
        // Arrange
        [$service, $handover, $giver] = $this->openHandover();
        $service->cancel($handover, $giver);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $service->confirm($handover, $giver);
    }

    /**
     * @return array{HandoverService, Handover, User, User, Request}
     */
    private function openHandover(bool $withGiver = true): array
    {
        $this->ledgerRows = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof LedgerEntry) {
                $this->ledgerRows[] = $entity;
            }
        });

        $service = new HandoverService($em, new LedgerService($em, $this->createStub(LedgerEntryRepository::class)), $this->createStub(ActivityService::class));

        $giver = $this->user(3);
        $receiver = $this->user(5);
        $copy = new Copy('book-group-1', 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));
        new ReflectionProperty(Copy::class, 'id')->setValue($copy, 9);
        $copy->setDonatedBy($giver);
        $copy->setHolder($withGiver ? $giver : null);
        $copy->setStatus(CopyStatus::Available);

        $request = new Request('book-group-1', 'book', 42, $receiver, new DateTimeImmutable('2026-08-05 09:00:00'));
        $request->setStatus(RequestStatus::Offered);
        $request->setOfferedCopy($copy);
        $request->setOfferedAt(new DateTimeImmutable('2026-08-06 09:00:00'));

        $handover = $service->open($copy, $withGiver ? $giver : null, $receiver, $request);

        return [$service, $handover, $giver, $receiver, $request];
    }

    /**
     * @return list<LedgerEntry>
     */
    private function rowsOfType(LedgerEntryType $type): array
    {
        return array_values(array_filter($this->ledgerRows, static fn(LedgerEntry $row): bool => $row->getEntryType() === $type));
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
