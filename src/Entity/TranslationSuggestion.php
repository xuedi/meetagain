<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\TranslationSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranslationSuggestionRepository::class)]
class TranslationSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne]
    private ?User $approvedBy = null;

    #[ORM\Column(length: 2)]
    private ?string $language = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $suggestion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Translation $translation = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $previous = null;

    #[ORM\Column(enumType: TranslationSuggestionStatus::class)]
    private ?TranslationSuggestionStatus $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
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

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    public function setSuggestion(string $suggestion): static
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    public function getTranslation(): ?Translation
    {
        return $this->translation;
    }

    public function setTranslation(?Translation $translation): static
    {
        $this->translation = $translation;

        return $this;
    }

    public function getPrevious(): ?string
    {
        return $this->previous;
    }

    public function setPrevious(string $previous): static
    {
        $this->previous = $previous;

        return $this;
    }

    public function getStatus(): ?TranslationSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(TranslationSuggestionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
