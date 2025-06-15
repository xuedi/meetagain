<?php

namespace App\Repository;

use App\Entity\Plugin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plugin>
 */
class PluginRepository extends ServiceEntityRepository
{
    public const string PLUGIN_LIST_KEY = 'PluginRepository::PLUGIN_LIST_KEY';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    public function findAllWithIdentKey(): array
    {
        $result = [];
        $plugins = $this->findAll();
        foreach ($plugins as $plugin) {
            $result[$plugin->getIdent()] = $plugin;
        }

        return $result;
    }

}
