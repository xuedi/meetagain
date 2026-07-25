<?php declare(strict_types=1);

namespace App\Localization;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;

/**
 * Shared count/fetch/delete for sources backed by a table with one row per (owner, locale).
 * Subclasses name the mapping and turn entities into rows.
 */
abstract readonly class AbstractLocalizedRowSource implements LocalizedContentSourceInterface
{
    private const int PREVIEW_LENGTH = 80;

    public function __construct(
        protected EntityManagerInterface $em,
    ) {}

    /** @return class-string */
    abstract protected function getEntityClass(): string;

    abstract protected function getLocaleField(): string;

    abstract protected function getOwnerField(): string;

    #[Override]
    public function countOutsideLocales(array $ownerIds, array $keepLocales): int
    {
        if ($ownerIds === [] || $keepLocales === []) {
            return 0;
        }

        return (int) $this
            ->baseQuery($ownerIds, $keepLocales)
            ->select('COUNT(row.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function deleteOutsideLocales(array $ownerIds, array $keepLocales): int
    {
        $entities = $this->fetchEntities($ownerIds, $keepLocales);
        if ($entities === []) {
            return 0;
        }

        foreach ($entities as $entity) {
            $this->em->remove($entity);
        }
        $this->em->flush();

        return count($entities);
    }

    /**
     * @param list<int> $ownerIds
     * @param list<string> $keepLocales
     * @return list<object>
     */
    protected function fetchEntities(array $ownerIds, array $keepLocales): array
    {
        if ($ownerIds === [] || $keepLocales === []) {
            return [];
        }

        return $this->baseQuery($ownerIds, $keepLocales)->select('row')->getQuery()->getResult();
    }

    protected function preview(?string $text): string
    {
        $plain = trim(strip_tags((string) $text));
        if (mb_strlen($plain) <= self::PREVIEW_LENGTH) {
            return $plain;
        }

        return mb_substr($plain, 0, self::PREVIEW_LENGTH) . '...';
    }

    /**
     * @param list<int> $ownerIds
     * @param list<string> $keepLocales
     */
    private function baseQuery(array $ownerIds, array $keepLocales): QueryBuilder
    {
        return $this->em
            ->createQueryBuilder()
            ->from($this->getEntityClass(), 'row')
            ->where(sprintf('row.%s IN (:ownerIds)', $this->getOwnerField()))
            ->andWhere(sprintf('row.%s NOT IN (:keepLocales)', $this->getLocaleField()))
            ->setParameter('ownerIds', $ownerIds)
            ->setParameter('keepLocales', $keepLocales);
    }
}
