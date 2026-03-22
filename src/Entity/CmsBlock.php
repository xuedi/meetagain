<?php declare(strict_types=1);

namespace App\Entity;

use App\Entity\BlockType\BlockType;
use App\Enum\CmsBlock\CmsBlockType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CmsBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2)]
    private ?string $language = null;

    #[ORM\Column(enumType: CmsBlockType::class)]
    private ?CmsBlockType $type = null;

    #[ORM\ManyToOne(inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cms $page = null; // TODO: rename to just cms (cms_id in DB)

    #[ORM\Column]
    private array $json = [];

    #[ORM\Column]
    private ?float $priority = null;

    #[ORM\ManyToOne]
    private ?Image $image = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getType(): ?CmsBlockType
    {
        return $this->type;
    }

    public function setType(CmsBlockType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPage(): ?Cms
    {
        return $this->page;
    }

    public function setPage(?Cms $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getJson(): array
    {
        return $this->json;
    }

    public function getBlockObject(): BlockType
    {
        $class = $this->getType()->getBlockClass();
        return $class::fromJson($this->getJson(), $this->getImage());
    }

    public function setJson(array $json): static
    {
        $this->json = $json;

        return $this;
    }

    public function getPriority(): ?float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): static
    {
        $this->image = $image;

        return $this;
    }
}
