<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Geo\Internal\Repository\DistrictRepository;

#[ORM\Entity(repositoryClass: DistrictRepository::class)]
#[ORM\Table(name: 'mod_geo_district')]
#[ORM\UniqueConstraint(name: 'uniq_geo_district_slug', columns: ['city_id', 'slug'])]
class District
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', nullable: false, onDelete: 'CASCADE')]
    private City $city;

    #[ORM\Column(unique: true, nullable: true)]
    private ?int $geonameId;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $asciiName;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $slug;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(nullable: true)]
    private ?int $population = null;

    /** @var Collection<int, DistrictTranslation> */
    #[ORM\OneToMany(targetEntity: DistrictTranslation::class, mappedBy: 'district', cascade: ['persist'])]
    private Collection $translations;

    public function __construct(City $city, ?int $geonameId, string $asciiName, string $slug)
    {
        $this->city = $city;
        $this->geonameId = $geonameId;
        $this->asciiName = $asciiName;
        $this->slug = $slug;
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function getGeonameId(): ?int
    {
        return $this->geonameId;
    }

    public function getAsciiName(): string
    {
        return $this->asciiName;
    }

    public function setAsciiName(string $asciiName): static
    {
        $this->asciiName = $asciiName;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function locate(?string $latitude, ?string $longitude): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        return $this;
    }

    public function getPopulation(): ?int
    {
        return $this->population;
    }

    public function setPopulation(?int $population): static
    {
        $this->population = $population;

        return $this;
    }

    /**
     * @return Collection<int, DistrictTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
