<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mod_geo_country_translation')]
#[ORM\UniqueConstraint(name: 'uniq_geo_country_translation', columns: ['country_id', 'language'])]
#[ORM\Index(name: 'idx_geo_country_translation_name', columns: ['language', 'name'])]
#[ORM\Index(name: 'idx_geo_country_translation_slug', columns: ['name_slug'])]
class CountryTranslation extends Translation
{
    #[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'country_id', nullable: false, onDelete: 'CASCADE')]
    private Country $country;

    public function __construct(Country $country, string $language, string $name, string $nameSlug)
    {
        parent::__construct($language, $name, $nameSlug);
        $this->country = $country;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }
}
