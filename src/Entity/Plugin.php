<?php

namespace App\Entity;

use App\Repository\PluginRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PluginRepository::class)]
class Plugin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private ?string $ident = null;

    #[ORM\Column(length: 16)]
    private ?string $version = null;

    #[ORM\Column(length: 16)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $installed = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $enabled = null;

    private bool $deleted = true;

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getIdent(): ?string
    {
        return $this->ident;
    }

    public function setIdent(string $ident): static
    {
        $this->ident = $ident;

        return $this;
    }

    public function isInstalled(): ?bool
    {
        return $this->installed;
    }

    public function setInstalled(bool $installed): static
    {
        $this->installed = $installed;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
