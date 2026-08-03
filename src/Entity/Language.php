<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Language
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2, unique: true)]
    private string $code;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\ManyToOne]
    private ?Image $tileImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tileGreeting = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $tileIntro = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tileCta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tileImageAlt = null;

    public function getId(): ?int
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

    public function getTileImage(): ?Image
    {
        return $this->tileImage;
    }

    public function setTileImage(?Image $tileImage): static
    {
        $this->tileImage = $tileImage;

        return $this;
    }

    public function getTileGreeting(): ?string
    {
        return $this->tileGreeting;
    }

    public function setTileGreeting(?string $tileGreeting): static
    {
        $this->tileGreeting = $tileGreeting;

        return $this;
    }

    public function getTileIntro(): ?string
    {
        return $this->tileIntro;
    }

    public function setTileIntro(?string $tileIntro): static
    {
        $this->tileIntro = $tileIntro;

        return $this;
    }

    public function getTileCta(): ?string
    {
        return $this->tileCta;
    }

    public function setTileCta(?string $tileCta): static
    {
        $this->tileCta = $tileCta;

        return $this;
    }

    public function getTileImageAlt(): ?string
    {
        return $this->tileImageAlt;
    }

    public function setTileImageAlt(?string $tileImageAlt): static
    {
        $this->tileImageAlt = $tileImageAlt;

        return $this;
    }
}
