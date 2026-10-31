<?php declare(strict_types=1);

namespace Module\Geo\Internal\Repository;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\CityTranslation;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Entity\CountryTranslation;
use Module\Geo\Internal\Entity\District;
use Module\Geo\Internal\Entity\DistrictTranslation;
use Module\Geo\Internal\Entity\State;
use Module\Geo\Internal\Entity\StateTranslation;
use Module\Geo\Internal\Entity\Translation;

readonly class TranslationRepository
{
    private const array CLASSES = [
        'country' => CountryTranslation::class,
        'state' => StateTranslation::class,
        'city' => CityTranslation::class,
        'district' => DistrictTranslation::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<int> $parentIds
     * @return array<int, array<string, Translation>> keyed by parent id, then language
     */
    public function findByParents(string $parent, array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('t', sprintf('IDENTITY(t.%s) AS parentId', $parent))
            ->from(self::CLASSES[$parent] ?? throw new InvalidArgumentException(sprintf('No translations for %s.', $parent)), 't')
            ->where(sprintf('t.%s IN (:ids)', $parent))
            ->setParameter('ids', $parentIds)
            ->getQuery()
            ->getResult();

        $byParent = [];
        foreach ($rows as $row) {
            $translation = $row[0];
            $byParent[(int) $row['parentId']][$translation->getLanguage()] = $translation;
        }

        return $byParent;
    }

    public function create(string $parent, int $parentId, string $language, string $name, string $nameSlug): Translation
    {
        $translation = match ($parent) {
            'country' => new CountryTranslation($this->entityManager->getReference(Country::class, $parentId), $language, $name, $nameSlug),
            'state' => new StateTranslation($this->entityManager->getReference(State::class, $parentId), $language, $name, $nameSlug),
            'city' => new CityTranslation($this->entityManager->getReference(City::class, $parentId), $language, $name, $nameSlug),
            'district' => new DistrictTranslation($this->entityManager->getReference(District::class, $parentId), $language, $name, $nameSlug),
            default => throw new InvalidArgumentException(sprintf('No translations for %s.', $parent)),
        };
        $this->entityManager->persist($translation);

        return $translation;
    }
}
