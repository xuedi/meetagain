<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mod_geo_district_translation')]
#[ORM\UniqueConstraint(name: 'uniq_geo_district_translation', columns: ['district_id', 'language'])]
#[ORM\Index(name: 'idx_geo_district_translation_name', columns: ['language', 'name'])]
#[ORM\Index(name: 'idx_geo_district_translation_slug', columns: ['name_slug'])]
class DistrictTranslation extends Translation
{
    #[ORM\ManyToOne(targetEntity: District::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'district_id', nullable: false, onDelete: 'CASCADE')]
    private District $district;

    public function __construct(District $district, string $language, string $name, string $nameSlug)
    {
        parent::__construct($language, $name, $nameSlug);
        $this->district = $district;
    }

    public function getDistrict(): District
    {
        return $this->district;
    }
}
