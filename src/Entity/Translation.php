<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(fields: ['language', 'placeholder'])]
#[ORM\Entity(repositoryClass: TranslationRepository::class)]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column]
    private null|\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $user = null;

    #[ORM\Column(length: 2)]
    private string $language;

    #[ORM\Column(length: 255)]
    private string $placeholder;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $translation = null;

    public function getId(): null|int
    {
        return $this->id;
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

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getTranslation(): null|string
    {
        return $this->translation;
    }

    public function setTranslation(null|string $translation): static
    {
        $this->translation = $translation;

        return $this;
    }

    public function getCreatedAt(): null|\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }
}
