<?php declare(strict_types=1);

namespace Plugin\Karaoke\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\Repository\SongRepository;
use Plugin\Karaoke\ValueObject\MediaLink;

#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\Table(name: 'plg_karaoke_song')]
class Song
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(length: 2)]
    private ?string $language = null;

    #[ORM\Column(enumType: MediaProvider::class)]
    private ?MediaProvider $mediaProvider = null;

    #[ORM\Column(length: 64)]
    private ?string $mediaId = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $offsetMs = 0;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    /** @var Collection<int, LyricLine> */
    #[ORM\OneToMany(targetEntity: LyricLine::class, mappedBy: 'song', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setArtist(?string $artist): static
    {
        $this->artist = $artist;

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

    public function getMediaProvider(): ?MediaProvider
    {
        return $this->mediaProvider;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function getMediaLink(): MediaLink
    {
        return new MediaLink($this->mediaProvider ?? MediaProvider::YouTube, (string) $this->mediaId);
    }

    public function setMediaLink(MediaLink $link): static
    {
        $this->mediaProvider = $link->provider;
        $this->mediaId = $link->id;

        return $this;
    }

    public function getOffsetMs(): int
    {
        return $this->offsetMs;
    }

    public function setOffsetMs(int $offsetMs): static
    {
        $this->offsetMs = $offsetMs;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    /** @return Collection<int, LyricLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(LyricLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setSong($this);
        }

        return $this;
    }

    public function removeLine(LyricLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function isTimed(): bool
    {
        return $this->lines->exists(static fn(int $key, LyricLine $line): bool => $line->getStartMs() !== null);
    }
}
