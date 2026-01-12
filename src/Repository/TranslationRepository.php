<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Translation>
 */
class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    public function getMatrix(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.id', 't.language', 't.placeholder', 't.translation')
            ->orderBy('t.placeholder', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $structuredList = [];
        foreach ($rows as $row) {
            $structuredList[$row['placeholder']][$row['language']] = [
                'id' => $row['id'],
                'value' => $row['translation'] ?? '',
            ];
        }

        return $structuredList;
    }

    public function buildKeyValueList(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.id', 't.translation')
            ->getQuery()
            ->getArrayResult();

        $list = [];
        foreach ($rows as $row) {
            $list[$row['id']] = $row['translation'];
        }

        return $list;
    }

    public function getUniqueList(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.language', 'LOWER(t.placeholder) AS placeholder')
            ->getQuery()
            ->getArrayResult();

        $list = [];
        foreach ($rows as $row) {
            $list[$row['language']][] = $row['placeholder'];
        }

        return $list;
    }

    public function getExportList(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.language', 't.placeholder', 't.translation')
            ->getQuery()
            ->getArrayResult();
    }
}
