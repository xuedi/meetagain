<?php declare(strict_types=1);

namespace App\Filter\TownHall;

final readonly class ScopeIntersection
{
    /**
     * @param iterable<array<int>|null> $idLists
     * @return array<int>|null
     */
    public function of(iterable $idLists): ?array
    {
        $result = null;

        foreach ($idLists as $ids) {
            if ($ids === null) {
                continue;
            }
            if ($ids === []) {
                return [];
            }

            $result = $result === null ? array_values($ids) : array_values(array_intersect($result, $ids));
            if ($result === []) {
                return [];
            }
        }

        return $result;
    }
}
