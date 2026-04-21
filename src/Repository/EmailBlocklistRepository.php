<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailBlocklistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailBlocklistEntry>
 */
class EmailBlocklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailBlocklistEntry::class);
    }

    public function isBlocked(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function findByEmail(string $email): ?EmailBlocklistEntry
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return $this->findOneBy(['email' => $normalized]);
    }

    /**
     * @return EmailBlocklistEntry[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
