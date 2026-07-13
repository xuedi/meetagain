<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\PluginSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PluginSettings>
 */
class PluginSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginSettings::class);
    }

    public function findOneByPluginKey(string $pluginKey): ?PluginSettings
    {
        return $this->findOneBy(['pluginKey' => $pluginKey]);
    }
}
