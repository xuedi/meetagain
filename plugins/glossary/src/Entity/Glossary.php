<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Glossary\Repository\GlossaryRepository;

#[ORM\Entity(repositoryClass: GlossaryRepository::class)]
#[ORM\Table(name: 'plg_glossary_glossary')]
class Glossary
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $phrase = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secondary = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $termLanguage = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    /** @var Collection<int, Definition> */
    #[ORM\OneToMany(targetEntity: Definition::class, mappedBy: 'glossary', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $definitions;

    /** @var array<string, string>|null */
    private ?array $submittedDefinitions = null;

    public function __construct()
    {
        $this->definitions = new ArrayCollection();
    }

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

    public function getSecondary(): ?string
    {
        return $this->secondary;
    }

    public function setSecondary(?string $secondary): static
    {
        $this->secondary = $secondary;

        return $this;
    }

    public function getTermLanguage(): ?string
    {
        return $this->termLanguage;
    }

    public function setTermLanguage(?string $termLanguage): static
    {
        $this->termLanguage = $termLanguage;

        return $this;
    }

    /** @return Collection<int, Definition> */
    public function getDefinitions(): Collection
    {
        return $this->definitions;
    }

    public function findDefinition(string $language): ?Definition
    {
        return $this->definitions->findFirst(static fn(int $key, Definition $definition): bool => $definition->getLanguage() === $language);
    }

    /** @return array<string, string> language => text, filled definitions only */
    public function getDefinitionMap(): array
    {
        $map = [];
        foreach ($this->definitions as $definition) {
            $text = (string) $definition->getText();
            if ($text !== '') {
                $map[(string) $definition->getLanguage()] = $text;
            }
        }

        return $map;
    }

    public function setDefinition(string $language, ?string $text): static
    {
        $text = trim((string) $text);
        $existing = $this->findDefinition($language);

        if ($text === '') {
            if ($existing !== null) {
                $this->definitions->removeElement($existing);
            }

            return $this;
        }

        if ($existing === null) {
            $existing = new Definition()
                ->setLanguage($language)
                ->setGlossary($this);
            $this->definitions->add($existing);
        }
        $existing->setText($text);

        return $this;
    }

    public function resolveDefinition(?string $locale, string $sourceLocale): string
    {
        $map = $this->getDefinitionMap();

        return $map[$locale ?? ''] ?? $map[$sourceLocale] ?? array_values($map)[0] ?? '';
    }

    /** @param array<string, string> $definitions */
    public function submitDefinitions(array $definitions): static
    {
        $this->submittedDefinitions = $definitions;

        return $this;
    }

    /** @return array<string, string>|null */
    public function getSubmittedDefinitions(): ?array
    {
        return $this->submittedDefinitions;
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
}
