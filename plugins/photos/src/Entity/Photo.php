<?php declare(strict_types=1);

namespace Plugin\Photos\Entity;

use App\Entity\Image;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Photos\Repository\PhotoRepository;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'plg_photos_photo')]
class Photo
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Image $image = null;

    /** @var Collection<int, PhotoTranslation> */
    #[ORM\OneToMany(targetEntity: PhotoTranslation::class, mappedBy: 'photo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $takenAt = null;

    /** @var array<string, scalar>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getVisibleImage(): ?Image
    {
        return $this->image?->getReported() === null ? $this->image : null;
    }

    /** @return Collection<int, PhotoTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PhotoTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPhoto($this);
        }

        return $this;
    }

    public function removeTranslation(PhotoTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getPhoto() === $this) {
            $translation->setPhoto(null);
        }

        return $this;
    }

    public function findTranslation(string $language): ?PhotoTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }

        return null;
    }

    public function getTranslatedTitle(string $language): string
    {
        return $this->findTranslation($language)?->getTitle() ?? $this->getAnyTranslatedTitle();
    }

    public function getTranslatedDescription(string $language): string
    {
        return $this->findTranslation($language)?->getDescription() ?? '';
    }

    public function getAnyTranslatedTitle(): string
    {
        $first = $this->translations->first();

        return $first !== false ? (string) $first->getTitle() : '';
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getTakenAt(): ?DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function setTakenAt(?DateTimeImmutable $takenAt): static
    {
        $this->takenAt = $takenAt;

        return $this;
    }

    /** @return array<string, scalar>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /** @param array<string, scalar>|null $meta */
    public function setMeta(?array $meta): static
    {
        $this->meta = $meta === [] ? null : $meta;

        return $this;
    }

    public function getCameraLabel(): string
    {
        $make = trim((string) ($this->meta['make'] ?? ''));
        $model = trim((string) ($this->meta['model'] ?? ''));

        if ($model !== '' && $make !== '' && !str_starts_with(strtolower($model), strtolower($make))) {
            return $make . ' ' . $model;
        }

        return $model !== '' ? $model : $make;
    }
}
