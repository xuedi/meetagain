<?php declare(strict_types=1);

namespace App\Entity;

use App\Entity\BlockType\BlockType;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Image as ImageBlockType;
use App\Entity\BlockType\Paragraph;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\Title;
use App\Repository\CmsBlockRepository;
use Doctrine\ORM\Mapping as ORM;
use Exception;

#[ORM\Entity(repositoryClass: CmsBlockRepository::class)]
class CmsBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 2)]
    private null|string $language = null;

    #[ORM\Column(enumType: CmsBlockTypes::class)]
    private null|CmsBlockTypes $Type = null;

    #[ORM\ManyToOne(inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false)]
    private null|Cms $page = null; // TODO: rename to just cms (cms_id in DB)

    #[ORM\Column]
    private array $json = [];

    #[ORM\Column]
    private null|float $priority = null;

    #[ORM\ManyToOne]
    private null|Image $image = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getLanguage(): null|string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getType(): null|CmsBlockTypes
    {
        return $this->Type;
    }

    public function setType(CmsBlockTypes $Type): static
    {
        $this->Type = $Type;

        return $this;
    }

    public function getPage(): null|Cms
    {
        return $this->page;
    }

    public function setPage(null|Cms $page): static
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
        return CmsBlockTypes::buildObject($this->getType(), $this->getJson());
    }

    public function setJson(array $json): static
    {
        $this->json = $json;

        return $this;
    }

    public function getPriority(): null|float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getImage(): null|Image
    {
        return $this->image;
    }

    public function setImage(null|Image $image): static
    {
        $this->image = $image;

        return $this;
    }
}
