<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mod_geo_city_translation')]
#[ORM\UniqueConstraint(name: 'uniq_geo_city_translation', columns: ['city_id', 'language'])]
#[ORM\Index(name: 'idx_geo_city_translation_name', columns: ['language', 'name'])]
#[ORM\Index(name: 'idx_geo_city_translation_slug', columns: ['name_slug'])]
class CityTranslation extends Translation
{
    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'city_id', nullable: false, onDelete: 'CASCADE')]
    private City $city;

    public function __construct(City $city, string $language, string $name, string $nameSlug)
    {
        parent::__construct($language, $name, $nameSlug);
        $this->city = $city;
    }

    public function getCity(): City
    {
        return $this->city;
    }
}
