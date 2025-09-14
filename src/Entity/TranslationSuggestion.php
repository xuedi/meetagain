<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\TranslationSuggestionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranslationSuggestionRepository::class)]
class TranslationSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private null|DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne]
    private null|User $approvedBy = null;

    #[ORM\Column(length: 2)]
    private null|string $language = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $suggestion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|Translation $translation = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $previous = null;

    #[ORM\Column(enumType: TranslationSuggestionStatus::class)]
    private null|TranslationSuggestionStatus $status = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getCreatedAt(): null|DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): null|User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(null|User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getApprovedAt(): null|DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(null|DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getApprovedBy(): null|User
    {
        return $this->approvedBy;
    }

    public function approvedByName(): string
    {
        return $this->approvedBy?->getName() ?? '';
    }

    public function setApprovedBy(null|User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
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

    public function getSuggestion(): null|string
    {
        return $this->suggestion;
    }

    public function setSuggestion(string $suggestion): static
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    public function getTranslation(): null|Translation
    {
        return $this->translation;
    }

    public function setTranslation(null|Translation $translation): static
    {
        $this->translation = $translation;

        return $this;
    }

    public function getPrevious(): null|string
    {
        return $this->previous;
    }

    public function setPrevious(string $previous): static
    {
        $this->previous = $previous;

        return $this;
    }

    public function getStatus(): null|TranslationSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(TranslationSuggestionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
