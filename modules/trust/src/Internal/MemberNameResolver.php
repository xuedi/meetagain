<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MemberNameResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<int> $userIds
     * @return array<int, string>
     */
    public function resolve(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.id', 'u.name')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) ($row['name'] ?? $row['id']);
        }

        return $names;
    }
}
