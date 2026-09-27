<?php declare(strict_types=1);

namespace Module\Email\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Email\Internal\Entity\EmailTemplate;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    public function findByIdentifier(string $identifier): ?EmailTemplate
    {
        return $this->findOneBy(['identifier' => $identifier]);
    }
}
