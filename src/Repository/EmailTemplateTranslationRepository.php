<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailTemplateTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
