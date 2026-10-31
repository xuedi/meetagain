<?php declare(strict_types=1);

namespace Module\Task\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Task\Internal\Entity\ActionSetting;

/**
 * @extends ServiceEntityRepository<ActionSetting>
 */
class ActionSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionSetting::class);
    }

    /**
     * @return array<string, string|int|bool|null> keyed by setting key
     */
    public function findValues(string $identifier): array
    {
        $values = [];
        foreach ($this->findBy(['identifier' => $identifier]) as $setting) {
            $values[$setting->getKey()] = $setting->getValue();
        }

        return $values;
    }

    /**
     * @param array<string, string|int|bool|null> $values keyed by setting key
     */
    public function store(string $identifier, array $values): void
    {
        $existing = [];
        foreach ($this->findBy(['identifier' => $identifier]) as $setting) {
            $existing[$setting->getKey()] = $setting;
        }

        $em = $this->getEntityManager();
        foreach ($values as $key => $value) {
            if (isset($existing[$key])) {
                $existing[$key]->change($value);
                continue;
            }
            $em->persist(new ActionSetting($identifier, $key, $value));
        }
        $em->flush();
    }
}
