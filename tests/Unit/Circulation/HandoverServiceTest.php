<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Activity\ActivityService;
use App\Circulation\HandoverService;
use App\Circulation\LedgerService;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationLedgerEntry;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationHandoverStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Repository\CirculationLedgerEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class HandoverServiceTest extends TestCase
{
    /** @var list<CirculationLedgerEntry> */
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
        self::assertSame(CirculationHandoverStatus::Open, $handover->getStatus());
        self::assertSame(CirculationCopyStatus::InHandover, $handover->getCopy()->getStatus());
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
        self::assertSame(CirculationHandoverStatus::Open, $handover->getStatus());
    }

    public function testBothSidesConfirmingMovesTheCopyAndFulfilsTheRequest(): void
    {
        // Arrange
        [$service, $handover, $giver, $receiver, $request] = $this->openHandover();

        // Act
        $service->confirm($handover, $giver);
        $service->confirm($handover, $receiver);

        // Assert
        self::assertSame(CirculationHandoverStatus::Completed, $handover->getStatus());
        self::assertSame($receiver, $handover->getCopy()->getHolder());
        self::assertSame(CirculationCopyStatus::Held, $handover->getCopy()->getStatus());
        self::assertNull($handover->getCopy()->getFinishedAt());
        self::assertSame(CirculationRequestStatus::Fulfilled, $request->getStatus());
        self::assertCount(1, $this->rowsOfType(CirculationLedgerEntryType::HandoverCompleted));
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
        self::assertSame(CirculationHandoverStatus::Completed, $handover->getStatus());
        self::assertSame($receiver, $handover->getCopy()->getHolder());
    }

    public function testCancellingReturnsTheCopyToTheGiverAndTheRequesterToTheQueue(): void
    {
        // Arrange
        [$service, $handover, $giver, , $request] = $this->openHandover();

        // Act
        $service->cancel($handover, $giver);

        // Assert
        self::assertSame(CirculationHandoverStatus::Cancelled, $handover->getStatus());
        self::assertSame($giver, $handover->getCopy()->getHolder());
        self::assertSame(CirculationCopyStatus::Available, $handover->getCopy()->getStatus());
        self::assertSame(CirculationRequestStatus::Waiting, $request->getStatus());
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
     * @return array{HandoverService, CirculationHandover, User, User, CirculationRequest}
     */
    private function openHandover(bool $withGiver = true): array
    {
        $this->ledgerRows = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof CirculationLedgerEntry) {
                $this->ledgerRows[] = $entity;
            }
        });

        $service = new HandoverService(
            $em,
            new LedgerService($em, $this->createStub(CirculationLedgerEntryRepository::class)),
            $this->createStub(ActivityService::class),
        );

        $giver = $this->user(3);
        $receiver = $this->user(5);
        $copy = new CirculationCopy('book-group-1', 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));
        new ReflectionProperty(CirculationCopy::class, 'id')->setValue($copy, 9);
        $copy->setDonatedBy($giver);
        $copy->setHolder($withGiver ? $giver : null);
        $copy->setStatus(CirculationCopyStatus::Available);

        $request = new CirculationRequest('book-group-1', 'book', 42, $receiver, new DateTimeImmutable('2026-08-05 09:00:00'));
        $request->setStatus(CirculationRequestStatus::Offered);
        $request->setOfferedCopy($copy);
        $request->setOfferedAt(new DateTimeImmutable('2026-08-06 09:00:00'));

        $handover = $service->open($copy, $withGiver ? $giver : null, $receiver, $request);

        return [$service, $handover, $giver, $receiver, $request];
    }

    /**
     * @return list<CirculationLedgerEntry>
     */
    private function rowsOfType(CirculationLedgerEntryType $type): array
    {
        return array_values(array_filter($this->ledgerRows, static fn(CirculationLedgerEntry $row): bool => $row->getEntryType() === $type));
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
