<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Internal;

use App\Activity\ActivityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\HandoverService;
use Module\Circulation\Internal\LedgerService;
use Module\Circulation\Internal\QueueService;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Module\Circulation\Internal\Repository\RequestRepository;
use Module\Circulation\Tests\Stub\Users;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class QueueServiceTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    /** @var list<LedgerEntry> */
    private array $ledgerRows = [];

    public function testNextInLineSkipsAnAlreadyOfferedRequestAndKeepsFifoOrder(): void
    {
        // Arrange
        $offered = $this->request(3, '2026-08-01 09:00:00', RequestStatus::Offered);
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
        $second->setStatus(RequestStatus::Cancelled);

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
        self::assertSame(RequestStatus::Offered, $request->getStatus());
        self::assertSame($copy, $request->getOfferedCopy());
        self::assertSame(CopyStatus::InHandover, $copy->getStatus());
        self::assertSame($request->getUser(), $handover->getToUser());
    }

    public function testACopyThatIsNotAvailableIsNeverOffered(): void
    {
        // Arrange
        $service = $this->service([$this->request(5, '2026-08-02 09:00:00')]);
        $copy = $this->availableCopy();
        $copy->setStatus(CopyStatus::Held);

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
        self::assertSame(CopyStatus::Available, $copy->getStatus());
    }

    public function testPassOnExpiresTheOfferAndRecordsIt(): void
    {
        // Arrange
        $request = $this->request(5, '2026-08-02 09:00:00', RequestStatus::Offered);
        $copy = $this->availableCopy();
        $request->setOfferedCopy($copy);
        $service = $this->service([$request]);

        // Act
        $service->passOn($request);

        // Assert
        self::assertSame(RequestStatus::Expired, $request->getStatus());
        self::assertNull($request->getOfferedCopy());
        self::assertCount(1, array_filter($this->ledgerRows, static fn(LedgerEntry $row): bool => $row->getEntryType() === LedgerEntryType::RequestExpired));
    }

    public function testReleasePutsAnOfferedRequestBackInTheQueue(): void
    {
        // Arrange
        $request = $this->request(5, '2026-08-02 09:00:00', RequestStatus::Offered);
        $request->setOfferedCopy($this->availableCopy());
        $service = $this->service([$request]);

        // Act
        $service->release($request);

        // Assert
        self::assertSame(RequestStatus::Waiting, $request->getStatus());
        self::assertNull($request->getOfferedCopy());
    }

    /**
     * @param list<Request> $queue
     */
    private function service(array $queue): QueueService
    {
        $this->ledgerRows = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof LedgerEntry) {
                $this->ledgerRows[] = $entity;
            }
        });

        $requests = $this->createStub(RequestRepository::class);
        $requests->method('findQueue')->willReturn($queue);

        $ledger = new LedgerService($em, $this->createStub(LedgerEntryRepository::class));

        return new QueueService($em, $requests, new HandoverService($em, $ledger, $this->createStub(ActivityService::class)), $ledger);
    }

    private function availableCopy(): Copy
    {
        $copy = new Copy(self::CONTEXT, 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));
        new ReflectionProperty(Copy::class, 'id')->setValue($copy, 9);
        $copy->setHolder(Users::withId(3));
        $copy->setStatus(CopyStatus::Available);

        return $copy;
    }

    private function request(int $userId, string $requestedAt, RequestStatus $status = RequestStatus::Waiting): Request
    {
        $request = new Request(self::CONTEXT, 'book', 42, Users::withId($userId), new DateTimeImmutable($requestedAt));
        new ReflectionProperty(Request::class, 'id')->setValue($request, $userId);
        $request->setStatus($status);

        return $request;
    }
}
