<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Internal\Entity\EmailBlocklistEntry;
use Module\Email\Internal\Repository\EmailBlocklistRepository;

final class Blocklist implements BlocklistInterface
{
    /** @var array<string, true>|null */
    private ?array $blockedSet = null;

    public function __construct(
        private readonly EmailBlocklistRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function isBlocked(string $email): bool
    {
        $key = strtolower(trim($email));
        if ($key === '') {
            return false;
        }

        if ($this->blockedSet === null) {
            $this->blockedSet = [];
            foreach ($this->repository->findAllOrdered() as $entry) {
                $this->blockedSet[strtolower(trim((string) $entry->getEmail()))] = true;
            }
        }

        return isset($this->blockedSet[$key]);
    }

    /**
     * @return array<EmailBlocklistEntry>
     */
    public function listEntries(): array
    {
        return $this->repository->findAllOrdered();
    }

    public function findEntry(string $email): ?EmailBlocklistEntry
    {
        return $this->repository->findByEmail($email);
    }

    public function reasonFor(string $email): ?string
    {
        return $this->repository->findByEmail($email)?->getReason();
    }

    public function add(string $email, string $reason): void
    {
        if ($this->repository->findByEmail($email) instanceof EmailBlocklistEntry) {
            return;
        }

        $entry = new EmailBlocklistEntry()
            ->setEmail($email)
            ->setReason($reason)
            ->setAddedAt(new DateTimeImmutable());
        $this->em->persist($entry);
        $this->em->flush();

        if ($this->blockedSet !== null) {
            $this->blockedSet[(string) $entry->getEmail()] = true;
        }
    }
}
