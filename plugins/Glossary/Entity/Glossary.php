<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Plugin\Glossary\Repository\GlossaryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GlossaryRepository::class)]
class Glossary
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $phrase = null;

    #[ORM\Column(length: 255)]
    private ?string $pinyin = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\Column(enumType: Category::class)]
    private ?Category $category = null;

    #[ORM\Column(nullable: true)]
    private ?array $suggestion = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $explanation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhrase(): ?string
    {
        return $this->phrase;
    }

    public function setPhrase(string $phrase): static
    {
        $this->phrase = $phrase;

        return $this;
    }

    public function getPinyin(): ?string
    {
        return $this->pinyin;
    }

    public function setPinyin(?string $pinyin): static
    {
        $this->pinyin = $pinyin;

        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getSuggestion(string $hash): Suggestion
    {
        foreach ($this->getSuggestions() as $suggestion) {
            if ($suggestion->getHash() === $hash) {
                return $suggestion;
            }
        }
        throw new InvalidArgumentException('Suggestion not found');
    }

    public function removeSuggestion(string $hash): int
    {
        $newList = [];
        foreach ($this->getSuggestions() as $suggestion) {
            if ($suggestion->getHash() !== $hash) {
                $newList[] = $suggestion->jsonSerialize();
            }
        }
        $this->suggestion = $newList;

        return count($this->suggestion);
    }

    public function getSuggestions(): array
    {
        if (empty($this->suggestion)) {
            return [];
        }
        $suggestions = [];
        foreach ($this->suggestion as $json) {
            $suggestions[] = Suggestion::fromJson($json);
        }
        return $suggestions;
    }

    public function addSuggestions(Suggestion $suggestion): static
    {
        $this->suggestion[] = $suggestion->jsonSerialize();

        return $this;
    }
}
