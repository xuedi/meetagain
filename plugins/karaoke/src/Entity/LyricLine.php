<?php declare(strict_types=1);

namespace Plugin\Karaoke\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plg_karaoke_lyric_line')]
#[ORM\UniqueConstraint(name: 'uniq_karaoke_line_song_position', columns: ['song_id', 'position'])]
class LyricLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Song $song = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(nullable: true)]
    private ?int $startMs = null;

    #[ORM\Column(length: 500)]
    private string $text = '';

    /** @var Collection<int, LineTranslation> */
    #[ORM\OneToMany(targetEntity: LineTranslation::class, mappedBy: 'line', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSong(): ?Song
    {
        return $this->song;
    }

    public function setSong(?Song $song): static
    {
        $this->song = $song;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getStartMs(): ?int
    {
        return $this->startMs;
    }

    public function setStartMs(?int $startMs): static
    {
        $this->startMs = $startMs;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /** @return Collection<int, LineTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function findTranslation(string $language): ?LineTranslation
    {
        return $this->translations->findFirst(static fn(int $key, LineTranslation $translation): bool => $translation->getLanguage() === $language);
    }

    public function setTranslation(string $language, ?string $text): void
    {
        $translation = $this->findTranslation($language);
        if ($text === null || $text === '') {
            if ($translation !== null) {
                $this->translations->removeElement($translation);
            }

            return;
        }

        if ($translation === null) {
            $translation = new LineTranslation();
            $translation->setLanguage($language);
            $translation->setLine($this);
            $this->translations->add($translation);
        }
        $translation->setText($text);
    }

    public function resolveTranslation(?string $locale, string $sourceLocale): ?string
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[(string) $translation->getLanguage()] = $translation->getText();
        }

        return $map[$locale ?? ''] ?? $map[$sourceLocale] ?? array_values($map)[0] ?? null;
    }
}
