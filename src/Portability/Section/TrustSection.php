<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Circulation\ContextResolver;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Module\Trust\Contract\GrantTransferInterface;
use Module\Trust\Contract\PortableGrant;
use Module\Trust\Contract\TrustLevel;
use Override;

readonly class TrustSection implements SectionInterface
{
    public function __construct(
        private GrantTransferInterface $grants,
        private UserRepository $userRepository,
        private ContextResolver $contextResolver,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'trust';
    }

    #[Override]
    public function getOrder(): int
    {
        return 95;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $itemTypes = $scope->circulationContexts;
        if ($itemTypes === []) {
            return [];
        }

        $grants = array_values(array_filter(
            $this->grants->exportGrants(array_map(strval(...), array_keys($itemTypes))),
            static fn(PortableGrant $grant): bool => (
                $scope->grantsId($grant->fromUserId, DataCategory::Interactions) && $scope->grantsId($grant->toUserId, DataCategory::Interactions)
            ),
        ));
        if ($grants === []) {
            return [];
        }

        $emails = $this->emails($grants);
        $rows = [];
        foreach ($grants as $grant) {
            $rows[] = [
                'item_type' => $itemTypes[$grant->context] ?? '',
                'from_email' => $emails[$grant->fromUserId] ?? null,
                'to_email' => $emails[$grant->toUserId] ?? null,
                'level' => $grant->level->value,
                'created_at' => $grant->createdAt->format(DateTimeInterface::ATOM),
                'updated_at' => $grant->updatedAt->format(DateTimeInterface::ATOM),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $a['item_type'] <=> $b['item_type']);

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
            $fromUserId = $context->resolveRef(User::class, $row['from_email'] ?? null)?->getId();
            $toUserId = $context->resolveRef(User::class, $row['to_email'] ?? null)?->getId();
            $level = TrustLevel::tryFrom((string) ($row['level'] ?? ''));

            $isIncomplete = $itemType === '' || $fromUserId === null || $toUserId === null || $level === null;
            if ($isIncomplete || $fromUserId === $toUserId) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $createdAt = $this->date($row['created_at'] ?? null) ?? new DateTimeImmutable();
            $this->grants->restoreGrant(
                new PortableGrant(
                    $this->contextResolver->resolve($itemType),
                    $fromUserId,
                    $toUserId,
                    $level,
                    $createdAt,
                    $this->date($row['updated_at'] ?? null) ?? $createdAt,
                ),
            );
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param list<PortableGrant> $grants
     * @return array<int, string>
     */
    private function emails(array $grants): array
    {
        $userIds = [];
        foreach ($grants as $grant) {
            $userIds[$grant->fromUserId] = $grant->fromUserId;
            $userIds[$grant->toUserId] = $grant->toUserId;
        }

        $emails = [];
        foreach ($this->userRepository->findBy(['id' => array_values($userIds)]) as $user) {
            $emails[(int) $user->getId()] = (string) $user->getEmail();
        }

        return $emails;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }
}
