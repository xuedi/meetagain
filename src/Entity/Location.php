<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 255)]
    private null|string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $description = null;

    #[ORM\Column(length: 255)]
    private null|string $street = null;

    #[ORM\Column(length: 32)]
    private null|string $city = null;

    #[ORM\Column(length: 8)]
    private null|string $postcode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $user = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private null|string $longitude = null;

    #[ORM\Column(length: 20, nullable: true)]
    private null|string $latitude = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getName(): null|string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStreet(): null|string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getCity(): null|string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostcode(): null|string
    {
        return $this->postcode;
    }

    public function setPostcode(string $postcode): static
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getUser(): null|User
    {
        return $this->user;
    }

    public function setUser(null|User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): null|DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLongitude(): null|string
    {
        return $this->longitude;
    }

    public function setLongitude(null|string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLatitude(): null|string
    {
        return $this->latitude;
    }

    public function setLatitude(null|string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }
}
