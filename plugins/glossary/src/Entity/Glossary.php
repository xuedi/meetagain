<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Plugin\Glossary\Repository\GlossaryRepository;

#[ORM\Entity(repositoryClass: GlossaryRepository::class)]
class Glossary
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 255)]
    private null|string $phrase = null;

    #[ORM\Column(length: 255)]
    private null|string $pinyin = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private null|int $createdBy = null;

    #[ORM\Column]
    private bool $approved = false;

    #[ORM\Column(enumType: Category::class)]
    private null|Category $category = null;

    #[ORM\Column(nullable: true)]
    private null|array $suggestion = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $explanation = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getPhrase(): null|string
    {
        return $this->phrase;
    }

    public function setPhrase(string $phrase): static
    {
        $this->phrase = $phrase;

        return $this;
    }

    public function getPinyin(): null|string
    {
        return $this->pinyin;
    }

    public function setPinyin(null|string $pinyin): static
    {
        $this->pinyin = $pinyin;

        return $this;
    }

    public function getExplanation(): null|string
    {
        return $this->explanation;
    }

    public function getExplanationShortened(int $length): null|string
    {
        return wordwrap($this->explanation, $length);
    }

    public function setExplanation(null|string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
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

    public function getCreatedBy(): null|int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(null|int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCategory(): null|Category
    {
        return $this->category;
    }

    public function setCategory(null|Category $category): static
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
