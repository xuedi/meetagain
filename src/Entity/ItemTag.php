<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ItemTagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemTagRepository::class)]
#[ORM\Table(name: 'item_tag')]
#[ORM\Index(name: 'idx_item_tag_type', columns: ['item_type', 'position'])]
class ItemTag
{
    public const int MAX_DEPTH = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $itemType = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $labels = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /** @return array<string, string> */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /** @param array<string, string> $labels */
    public function setLabels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    public function setLabel(string $locale, string $label): static
    {
        $this->labels[$locale] = $label;

        return $this;
    }

    public function getLabel(?string $locale, string $sourceLocale): string
    {
        if ($locale !== null && ($this->labels[$locale] ?? '') !== '') {
            return $this->labels[$locale];
        }

        if (($this->labels[$sourceLocale] ?? '') !== '') {
            return $this->labels[$sourceLocale];
        }

        foreach ($this->labels as $label) {
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    /** @return list<self> nearest parent first */
    public function getAncestors(): array
    {
        $ancestors = [];
        $walked = [spl_object_id($this) => true];
        for ($current = $this->parent; $current !== null; $current = $current->parent) {
            if (isset($walked[spl_object_id($current)])) {
                break;
            }

            $walked[spl_object_id($current)] = true;
            $ancestors[] = $current;
        }

        return $ancestors;
    }

    public function getDepth(): int
    {
        return count($this->getAncestors()) + 1;
    }
}
