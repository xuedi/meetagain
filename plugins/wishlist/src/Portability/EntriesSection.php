<?php declare(strict_types=1);

namespace Plugin\Wishlist\Portability;

use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PluginSectionInterface;
use App\Portability\Scope;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Wishlist\Entity\WishlistEntry;

readonly class EntriesSection implements PluginSectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'wishlist';
    }

    #[Override]
    public function getOrder(): int
    {
        return 100;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'wishlist';
    }

    #[Override]
    public function getKindLabels(): array
    {
        return [$this->getKey() => 'wishlist_portability.kind_entries'];
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $itemIds = $scope->itemIds;
        ksort($itemIds);

        $entries = [];
        foreach ($itemIds as $itemType => $ids) {
            if ($ids === []) {
                continue;
            }

            foreach ($this->em->getRepository(WishlistEntry::class)->findBy(['itemType' => $itemType, 'itemId' => $ids], ['id' => 'ASC']) as $entry) {
                if (!$scope->grantsId($entry->getUserId(), DataCategory::Collections)) {
                    continue;
                }

                $entries[] = $entry;
            }
        }

        $emails = $this->emails($entries);
        $rows = [];
        foreach ($entries as $entry) {
            $email = $emails[(int) $entry->getUserId()] ?? null;
            if ($email === null) {
                continue;
            }

            $rows[] = [
                'email' => $email,
                'item_type' => $entry->getItemType(),
                'item_ref' => $entry->getItemId(),
                'priority_counter' => $entry->getPriorityCounter(),
                'created_at' => $entry->getCreatedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemType = (string) ($row['item_type'] ?? '');
            if (!$context->knowsItemType($itemType)) {
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $itemId = $context->resolveItem($itemType, $row['item_ref'] ?? null);
            $userId = $context->resolveRef(User::class, $row['email'] ?? null)?->getId();
            if ($itemId === null || $userId === null) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $existing = $this->em->getRepository(WishlistEntry::class)->findOneBy(['userId' => $userId, 'itemType' => $itemType, 'itemId' => $itemId]);
            if ($existing !== null) {
                $context->count($this->getKey(), Outcome::Matched);
                continue;
            }

            $createdAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) ($row['created_at'] ?? ''));
            $entry = new WishlistEntry()
                ->setUserId($userId)
                ->setItemType($itemType)
                ->setItemId($itemId)
                ->setPriorityCounter((int) ($row['priority_counter'] ?? 0))
                ->setCreatedAt($createdAt === false ? new DateTimeImmutable() : $createdAt);

            $this->em->persist($entry);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param list<WishlistEntry> $entries
     * @return array<int, string>
     */
    private function emails(array $entries): array
    {
        $userIds = array_values(array_unique(array_map(static fn(WishlistEntry $entry): int => (int) $entry->getUserId(), $entries)));
        if ($userIds === []) {
            return [];
        }

        $emails = [];
        foreach ($this->userRepository->findBy(['id' => $userIds]) as $user) {
            $emails[(int) $user->getId()] = (string) $user->getEmail();
        }

        return $emails;
    }
}
