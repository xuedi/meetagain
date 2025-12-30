<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Language>
 */
class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    /**
     * @return string[] Array of enabled language codes ordered by sortOrder
     */
    public function getEnabledCodes(): array
    {
        return array_column(
            $this->createQueryBuilder('l')
                ->select('l.code')
                ->where('l.enabled = :enabled')
                ->setParameter('enabled', true)
                ->orderBy('l.sortOrder', 'ASC')
                ->getQuery()
                ->getArrayResult(),
            'code'
        );
    }

    /**
     * @return Language[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['sortOrder' => 'ASC']);
    }

    public function findByCode(string $code): ?Language
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function isValidCode(string $code): bool
    {
        return $this->findOneBy(['code' => $code, 'enabled' => true]) !== null;
    }
}
