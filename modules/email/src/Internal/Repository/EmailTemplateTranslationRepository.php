<?php declare(strict_types=1);

namespace Module\Email\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Email\Internal\Entity\EmailTemplateTranslation;

/**
 * @extends ServiceEntityRepository<EmailTemplateTranslation>
 */
class EmailTemplateTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplateTranslation::class);
    }
}
