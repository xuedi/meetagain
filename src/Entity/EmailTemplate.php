<?php declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $identifier = null;

    #[ORM\Column(type: Types::JSON)]
    private array $availableVariables = [];

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, EmailTemplateTranslation>
     */
    #[ORM\OneToMany(targetEntity: EmailTemplateTranslation::class, mappedBy: 'emailTemplate', fetch: 'EAGER')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    public function setAvailableVariables(array $availableVariables): static
    {
        $this->availableVariables = $availableVariables;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, EmailTemplateTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(EmailTemplateTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setEmailTemplate($this);
        }

        return $this;
    }

    public function removeTranslation(EmailTemplateTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getEmailTemplate() === $this) {
            $translation->setEmailTemplate(null);
        }

        return $this;
    }

    public function findTranslation(string $language): ?EmailTemplateTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }

        return null;
    }

    public function getSubject(string $language): string
    {
        return $this->findTranslation($language)?->getSubject() ?? '';
    }

    public function getBody(string $language): string
    {
        return $this->findTranslation($language)?->getBody() ?? '';
    }
}
