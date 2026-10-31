<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Geo\Internal\Repository\CountryRepository;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\Table(name: 'mod_geo_country')]
#[ORM\Index(name: 'idx_geo_country_name_slug', columns: ['name_slug'])]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2, unique: true)]
    private string $iso2;

    #[ORM\Column(length: 3, unique: true)]
    private string $iso3;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $nameSlug;

    #[ORM\Column(nullable: true)]
    private ?int $numericCode = null;

    #[ORM\Column(unique: true)]
    private int $geonameId;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneCode = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currencyCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $continent = null;

    #[ORM\Column(nullable: true)]
    private ?int $capitalGeonameId = null;

    #[ORM\Column]
    private int $population = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $bboxNorth = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $bboxSouth = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $bboxEast = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $bboxWest = null;

    /** @var Collection<int, CountryTranslation> */
    #[ORM\OneToMany(targetEntity: CountryTranslation::class, mappedBy: 'country', cascade: ['persist'])]
    private Collection $translations;

    public function __construct(int $geonameId, string $iso2, string $iso3, string $name, string $nameSlug)
    {
        $this->geonameId = $geonameId;
        $this->iso2 = $iso2;
        $this->iso3 = $iso3;
        $this->name = $name;
        $this->nameSlug = $nameSlug;
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGeonameId(): int
    {
        return $this->geonameId;
    }

    public function getIso2(): string
    {
        return $this->iso2;
    }

    public function getIso3(): string
    {
        return $this->iso3;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameSlug(): string
    {
        return $this->nameSlug;
    }

    public function rename(string $iso3, string $name, string $nameSlug): void
    {
        $this->iso3 = $iso3;
        $this->name = $name;
        $this->nameSlug = $nameSlug;
    }

    public function getNumericCode(): ?int
    {
        return $this->numericCode;
    }

    public function setNumericCode(?int $numericCode): static
    {
        $this->numericCode = $numericCode;

        return $this;
    }

    public function getPhoneCode(): ?string
    {
        return $this->phoneCode;
    }

    public function setPhoneCode(?string $phoneCode): static
    {
        $this->phoneCode = $phoneCode;

        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): static
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getContinent(): ?string
    {
        return $this->continent;
    }

    public function setContinent(?string $continent): static
    {
        $this->continent = $continent;

        return $this;
    }

    public function getCapitalGeonameId(): ?int
    {
        return $this->capitalGeonameId;
    }

    public function setCapitalGeonameId(?int $capitalGeonameId): static
    {
        $this->capitalGeonameId = $capitalGeonameId;

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

    public function getBboxNorth(): ?string
    {
        return $this->bboxNorth;
    }

    public function getBboxSouth(): ?string
    {
        return $this->bboxSouth;
    }

    public function getBboxEast(): ?string
    {
        return $this->bboxEast;
    }

    public function getBboxWest(): ?string
    {
        return $this->bboxWest;
    }

    public function bound(?string $north, ?string $south, ?string $east, ?string $west): static
    {
        $this->bboxNorth = $north;
        $this->bboxSouth = $south;
        $this->bboxEast = $east;
        $this->bboxWest = $west;

        return $this;
    }

    /**
     * @return Collection<int, CountryTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
