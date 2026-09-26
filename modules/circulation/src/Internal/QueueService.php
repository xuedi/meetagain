<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\Repository\RequestRepository;

final readonly class QueueService
{
    public const int OFFER_WINDOW_DAYS = 7;

    public function __construct(
        private EntityManagerInterface $em,
        private RequestRepository $requests,
        private HandoverService $handovers,
        private LedgerService $ledger,
    ) {}

    /**
     * @return list<Request> oldest first
     */
    public function getQueue(string $context, string $itemType, int $itemId): array
    {
        return $this->requests->findQueue($context, $itemType, $itemId);
    }

    public function nextInLine(string $context, string $itemType, int $itemId): ?Request
    {
        foreach ($this->requests->findQueue($context, $itemType, $itemId) as $request) {
            if ($request->getStatus() === RequestStatus::Waiting) {
                return $request;
            }
        }

        return null;
    }

    public function positionOf(Request $request): int
    {
        $queue = $this->requests->findQueue($request->getContext(), $request->getItemType(), $request->getItemId());
        foreach ($queue as $index => $candidate) {
            if ($candidate->getId() === $request->getId()) {
                return $index + 1;
            }
        }

        return 0;
    }

    public function offerToNext(Copy $copy): ?Handover
    {
        if ($copy->getStatus() !== CopyStatus::Available) {
            return null;
        }

        $request = $this->nextInLine($copy->getContext(), $copy->getItemType(), $copy->getItemId());
        if ($request === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $request->setStatus(RequestStatus::Offered);
        $request->setOfferedCopy($copy);
        $request->setOfferedAt($now);
        $this->em->flush();

        return $this->handovers->open($copy, $copy->getHolder(), $request->getUser(), $request);
    }

    public function passOn(Request $request, ?Copy $copy = null): void
    {
        $copy ??= $request->getOfferedCopy();
        $request->setStatus(RequestStatus::Expired);
        $request->setOfferedCopy(null);
        $request->setOfferedAt(null);
        $this->em->flush();

        $this->ledger->append(
            LedgerEntryType::RequestExpired,
            $request->getContext(),
            $request->getItemType(),
            $request->getItemId(),
            new DateTimeImmutable(),
            $copy?->getId(),
            null,
            $request->getUser()->getId(),
        );
    }

    public function release(Request $request): void
    {
        if (!$request->isOpen()) {
            return;
        }

        $request->setStatus(RequestStatus::Waiting);
        $request->setOfferedCopy(null);
        $request->setOfferedAt(null);
        $this->em->flush();
    }

    /**
     * @return list<Request>
     */
    public function findStaleOffers(): array
    {
        return $this->requests->findOffersOlderThan(new DateTimeImmutable(sprintf('-%d days', self::OFFER_WINDOW_DAYS)));
    }
}
