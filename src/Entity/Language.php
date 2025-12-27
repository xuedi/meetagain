<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\LanguageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LanguageRepository::class)]
class Language
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 2, unique: true)]
    private string $code;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\ManyToOne]
    private null|Image $tileImage = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getTileImage(): null|Image
    {
        return $this->tileImage;
    }

    public function setTileImage(null|Image $tileImage): static
    {
        $this->tileImage = $tileImage;

        return $this;
    }
}
