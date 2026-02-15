<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_cms_location', columns: ['cms_id', 'location'])]
class CmsMenuLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'menuLocations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cms $cms = null;

    #[ORM\Column(enumType: MenuLocation::class)]
    private ?MenuLocation $location = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCms(): ?Cms
    {
        return $this->cms;
    }

    public function setCms(?Cms $cms): static
    {
        $this->cms = $cms;

        return $this;
    }

    public function getLocation(): ?MenuLocation
    {
        return $this->location;
    }

    public function setLocation(MenuLocation $location): static
    {
        $this->location = $location;

        return $this;
    }
}
