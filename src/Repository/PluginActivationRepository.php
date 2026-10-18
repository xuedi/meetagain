<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\PluginActivation;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PluginActivation>
 */
class PluginActivationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginActivation::class);
    }

    /**
     * @return array<string, bool>
     */
    public function stateByKey(): array
    {
        $state = [];
        foreach ($this->findAll() as $activation) {
            $state[$activation->getPluginKey()] = $activation->isEnabled();
        }

        return $state;
    }

    public function save(string $pluginKey, bool $enabled, DateTimeImmutable $changedAt): void
    {
        $activation = $this->find($pluginKey);
        if ($activation === null) {
            $this->getEntityManager()->persist(new PluginActivation($pluginKey, $enabled, $changedAt));
        } else {
            $activation->switchTo($enabled, $changedAt);
        }
        $this->getEntityManager()->flush();
    }
}
