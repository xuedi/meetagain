<?php declare(strict_types=1);

namespace App\Circulation;

use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationRequest;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Repository\CirculationRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class QueueService
{
    public const int OFFER_WINDOW_DAYS = 7;

    public function __construct(
        private EntityManagerInterface $em,
        private CirculationRequestRepository $requests,
        private HandoverService $handovers,
        private LedgerService $ledger,
    ) {}

    /**
     * @return list<CirculationRequest> oldest first
     */
    public function getQueue(string $context, string $itemType, int $itemId): array
    {
        return $this->requests->findQueue($context, $itemType, $itemId);
    }

    public function nextInLine(string $context, string $itemType, int $itemId): ?CirculationRequest
    {
        foreach ($this->requests->findQueue($context, $itemType, $itemId) as $request) {
            if ($request->getStatus() === CirculationRequestStatus::Waiting) {
                return $request;
            }
        }

        return null;
    }

    public function positionOf(CirculationRequest $request): int
    {
        $queue = $this->requests->findQueue($request->getContext(), $request->getItemType(), $request->getItemId());
        foreach ($queue as $index => $candidate) {
            if ($candidate->getId() === $request->getId()) {
                return $index + 1;
            }
        }

        return 0;
    }

    public function offerToNext(CirculationCopy $copy): ?CirculationHandover
    {
        if ($copy->getStatus() !== CirculationCopyStatus::Available) {
            return null;
        }

        $request = $this->nextInLine($copy->getContext(), $copy->getItemType(), $copy->getItemId());
        if ($request === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $request->setStatus(CirculationRequestStatus::Offered);
        $request->setOfferedCopy($copy);
        $request->setOfferedAt($now);
        $this->em->flush();

        return $this->handovers->open($copy, $copy->getHolder(), $request->getUser(), $request);
    }

    public function passOn(CirculationRequest $request, ?CirculationCopy $copy = null): void
    {
        $copy ??= $request->getOfferedCopy();
        $request->setStatus(CirculationRequestStatus::Expired);
        $request->setOfferedCopy(null);
        $request->setOfferedAt(null);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::RequestExpired,
            $request->getContext(),
            $request->getItemType(),
            $request->getItemId(),
            new DateTimeImmutable(),
            $copy?->getId(),
            null,
            $request->getUser()->getId(),
        );
    }

    public function release(CirculationRequest $request): void
    {
        if (!$request->isOpen()) {
            return;
        }

        $request->setStatus(CirculationRequestStatus::Waiting);
        $request->setOfferedCopy(null);
        $request->setOfferedAt(null);
        $this->em->flush();
    }

    /**
     * @return list<CirculationRequest>
     */
    public function findStaleOffers(): array
    {
        return $this->requests->findOffersOlderThan(new DateTimeImmutable(sprintf('-%d days', self::OFFER_WINDOW_DAYS)));
    }
}
