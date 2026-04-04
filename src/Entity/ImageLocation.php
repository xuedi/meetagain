<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageLocationRepository::class)]
#[ORM\Table(name: 'image_location')]
#[ORM\UniqueConstraint(name: 'uq_image_location', columns: ['image_id', 'location_type', 'location_id'])]
class ImageLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Image $image = null;

    #[ORM\Column(type: 'smallint', enumType: ImageType::class)]
    private ?ImageType $locationType = null;

    #[ORM\Column]
    private ?int $locationId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getLocationType(): ?ImageType
    {
        return $this->locationType;
    }

    public function setLocationType(ImageType $locationType): static
    {
        $this->locationType = $locationType;

        return $this;
    }

    public function getLocationId(): ?int
    {
        return $this->locationId;
    }

    public function setLocationId(int $locationId): static
    {
        $this->locationId = $locationId;

        return $this;
    }
}
