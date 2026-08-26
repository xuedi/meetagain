<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Activity\ActivityService;
use App\Circulation\HandoverService;
use App\Circulation\LedgerService;
use App\Circulation\QueueService;
use App\Entity\CirculationCopy;
use App\Entity\CirculationLedgerEntry;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Repository\CirculationLedgerEntryRepository;
use App\Repository\CirculationRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class QueueServiceTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    /** @var list<CirculationLedgerEntry> */
    private array $ledgerRows = [];

    public function testNextInLineSkipsAnAlreadyOfferedRequestAndKeepsFifoOrder(): void
    {
        // Arrange
        $offered = $this->request(3, '2026-08-01 09:00:00', CirculationRequestStatus::Offered);
        $first = $this->request(5, '2026-08-02 09:00:00');
        $second = $this->request(7, '2026-08-03 09:00:00');
        $service = $this->service([$offered, $first, $second]);

        // Act
        $next = $service->nextInLine(self::CONTEXT, 'book', 42);

        // Assert
        self::assertSame($first, $next);
    }

    public function testPositionIsTheOneBasedPlaceInTheQueue(): void
    {
        // Arrange
        $first = $this->request(3, '2026-08-01 09:00:00');
        $second = $this->request(5, '2026-08-02 09:00:00');
        $third = $this->request(7, '2026-08-03 09:00:00');
        $service = $this->service([$first, $second, $third]);

        // Act + Assert
        self::assertSame(1, $service->positionOf($first));
        self::assertSame(3, $service->positionOf($third));
    }

    public function testLeavingTheMiddleOfTheQueueMovesEveryoneBehindUp(): void
    {
        // Arrange
        $first = $this->request(3, '2026-08-01 09:00:00');
        $second = $this->request(5, '2026-08-02 09:00:00');
        $third = $this->request(7, '2026-08-03 09:00:00');
        $service = $this->service([$first, $third]);
        $second->setStatus(CirculationRequestStatus::Cancelled);

        // Act
        $position = $service->positionOf($third);

        // Assert
        self::assertSame(2, $position);
    }

    public function testOfferToNextOpensAHandoverForTheFirstWaitingMember(): void
    {
        // Arrange
        $request = $this->request(5, '2026-08-02 09:00:00');
        $service = $this->service([$request]);
        $copy = $this->availableCopy();

        // Act
        $handover = $service->offerToNext($copy);

        // Assert
        self::assertNotNull($handover);
        self::assertSame(CirculationRequestStatus::Offered, $request->getStatus());
        self::assertSame($copy, $request->getOfferedCopy());
        self::assertSame(CirculationCopyStatus::InHandover, $copy->getStatus());
        self::assertSame($request->getUser(), $handover->getToUser());
    }

    public function testACopyThatIsNotAvailableIsNeverOffered(): void
    {
        // Arrange
        $service = $this->service([$this->request(5, '2026-08-02 09:00:00')]);
        $copy = $this->availableCopy();
        $copy->setStatus(CirculationCopyStatus::Held);

        // Act
        $handover = $service->offerToNext($copy);

        // Assert
        self::assertNull($handover);
    }

    public function testAnEmptyQueueLeavesTheCopyAvailable(): void
    {
        // Arrange
        $service = $this->service([]);
        $copy = $this->availableCopy();

        // Act
        $handover = $service->offerToNext($copy);

        // Assert
        self::assertNull($handover);
        self::assertSame(CirculationCopyStatus::Available, $copy->getStatus());
    }

    public function testPassOnExpiresTheOfferAndRecordsIt(): void
    {
        // Arrange
        $request = $this->request(5, '2026-08-02 09:00:00', CirculationRequestStatus::Offered);
        $copy = $this->availableCopy();
        $request->setOfferedCopy($copy);
        $service = $this->service([$request]);

        // Act
        $service->passOn($request);

        // Assert
        self::assertSame(CirculationRequestStatus::Expired, $request->getStatus());
        self::assertNull($request->getOfferedCopy());
        self::assertCount(1, array_filter($this->ledgerRows, static fn(CirculationLedgerEntry $row): bool => $row->getEntryType() === CirculationLedgerEntryType::RequestExpired));
    }

    public function testReleasePutsAnOfferedRequestBackInTheQueue(): void
    {
        // Arrange
        $request = $this->request(5, '2026-08-02 09:00:00', CirculationRequestStatus::Offered);
        $request->setOfferedCopy($this->availableCopy());
        $service = $this->service([$request]);

        // Act
        $service->release($request);

        // Assert
        self::assertSame(CirculationRequestStatus::Waiting, $request->getStatus());
        self::assertNull($request->getOfferedCopy());
    }

    /**
     * @param list<CirculationRequest> $queue
     */
    private function service(array $queue): QueueService
    {
        $this->ledgerRows = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof CirculationLedgerEntry) {
                $this->ledgerRows[] = $entity;
            }
        });

        $requests = $this->createStub(CirculationRequestRepository::class);
        $requests->method('findQueue')->willReturn($queue);

        $ledger = new LedgerService($em, $this->createStub(CirculationLedgerEntryRepository::class));

        return new QueueService(
            $em,
            $requests,
            new HandoverService($em, $ledger, $this->createStub(ActivityService::class)),
            $ledger,
        );
    }

    private function availableCopy(): CirculationCopy
    {
        $copy = new CirculationCopy(self::CONTEXT, 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));
        new ReflectionProperty(CirculationCopy::class, 'id')->setValue($copy, 9);
        $copy->setHolder($this->user(3));
        $copy->setStatus(CirculationCopyStatus::Available);

        return $copy;
    }

    private function request(int $userId, string $requestedAt, CirculationRequestStatus $status = CirculationRequestStatus::Waiting): CirculationRequest
    {
        $request = new CirculationRequest(self::CONTEXT, 'book', 42, $this->user($userId), new DateTimeImmutable($requestedAt));
        new ReflectionProperty(CirculationRequest::class, 'id')->setValue($request, $userId);
        $request->setStatus($status);

        return $request;
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
