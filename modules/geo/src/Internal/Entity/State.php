<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Geo\Internal\Repository\StateRepository;

#[ORM\Entity(repositoryClass: StateRepository::class)]
#[ORM\Table(name: 'mod_geo_state')]
#[ORM\UniqueConstraint(name: 'uniq_geo_state_code', columns: ['country_id', 'code'])]
class State
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'country_id', nullable: false)]
    private Country $country;

    #[ORM\Column(length: 20)]
    private string $code;

    #[ORM\Column(unique: true)]
    private int $geonameId;

    #[ORM\Column(length: Translation::MAX_NAME_LENGTH)]
    private string $asciiName;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(nullable: true)]
    private ?int $population = null;

    /** @var Collection<int, StateTranslation> */
    #[ORM\OneToMany(targetEntity: StateTranslation::class, mappedBy: 'state', cascade: ['persist'])]
    private Collection $translations;

    public function __construct(Country $country, string $code, int $geonameId, string $asciiName)
    {
        $this->country = $country;
        $this->code = $code;
        $this->geonameId = $geonameId;
        $this->asciiName = $asciiName;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getGeonameId(): int
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
     * @return Collection<int, StateTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
