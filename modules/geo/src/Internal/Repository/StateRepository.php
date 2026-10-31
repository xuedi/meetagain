<?php declare(strict_types=1);

namespace Module\Geo\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Geo\Internal\Entity\State;

/**
 * @extends ServiceEntityRepository<State>
 */
class StateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, State::class);
    }

    /**
     * @return array<string, State> keyed by `ISO2.code`
     */
    public function findAllByCode(): array
    {
        $states = $this->createQueryBuilder('s')->join('s.country', 'co')->addSelect('co')->getQuery()->getResult();
        $byCode = [];
        foreach ($states as $state) {
            $byCode[$state->getCountry()->getIso2() . '.' . $state->getCode()] = $state;
        }

        return $byCode;
    }

    /**
     * @return array<int, int> state id keyed by GeoNames id
     */
    public function findIdsOfCountry(int $countryId): array
    {
        $rows = $this
            ->createQueryBuilder('s')
            ->select('s.id', 's.geonameId')
            ->where('s.country = :country')
            ->setParameter('country', $countryId)
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'id', 'geonameId'));
    }
}
