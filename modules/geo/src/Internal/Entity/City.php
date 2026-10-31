<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Geo\Internal\Repository\CityRepository;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'mod_geo_city')]
#[ORM\UniqueConstraint(name: 'uniq_geo_city_slug', columns: ['country_id', 'slug'])]
#[ORM\Index(name: 'idx_geo_city_name_slug', columns: ['name_slug'])]
#[ORM\Index(name: 'idx_geo_city_population', columns: ['population'])]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'country_id', nullable: false)]
    private Country $country;

    #[ORM\ManyToOne(targetEntity: State::class)]
    #[ORM\JoinColumn(name: 'state_id', nullable: true)]
    private ?State $state = null;

    #[ORM\Column(unique: true)]
    private int $geonameId;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $asciiName;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $slug;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $nameSlug;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $latitude;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $longitude;

    #[ORM\Column(length: 40)]
    private string $timezone;

    #[ORM\Column]
    private int $population;

    #[ORM\Column(length: 10)]
    private string $featureCode;

    /** @var Collection<int, CityTranslation> */
    #[ORM\OneToMany(targetEntity: CityTranslation::class, mappedBy: 'city', cascade: ['persist'])]
    private Collection $translations;

    public function __construct(
        Country $country,
        int $geonameId,
        string $asciiName,
        string $slug,
        string $nameSlug,
        string $latitude,
        string $longitude,
        string $timezone,
        int $population,
        string $featureCode,
    ) {
        $this->country = $country;
        $this->geonameId = $geonameId;
        $this->asciiName = $asciiName;
        $this->slug = $slug;
        $this->nameSlug = $nameSlug;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->timezone = $timezone;
        $this->population = $population;
        $this->featureCode = $featureCode;
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getGeonameId(): int
    {
        return $this->geonameId;
    }

    public function getAsciiName(): string
    {
        return $this->asciiName;
    }

    public function rename(string $asciiName, string $nameSlug): static
    {
        $this->asciiName = $asciiName;
        $this->nameSlug = $nameSlug;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getNameSlug(): string
    {
        return $this->nameSlug;
    }

    public function getLatitude(): string
    {
        return $this->latitude;
    }

    public function getLongitude(): string
    {
        return $this->longitude;
    }

    public function locate(string $latitude, string $longitude): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getPopulation(): int
    {
        return $this->population;
    }

    public function setPopulation(int $population): static
    {
        $this->population = $population;

        return $this;
    }

    public function getFeatureCode(): string
    {
        return $this->featureCode;
    }

    public function setFeatureCode(string $featureCode): static
    {
        $this->featureCode = $featureCode;

        return $this;
    }

    /**
     * @return Collection<int, CityTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
