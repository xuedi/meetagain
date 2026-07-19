<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributionStatus;
use App\Enum\ImageReportReason;
use App\Enum\ImageType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 8)]
    private ?string $extension = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $size = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $hash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $altTranslations = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $attribution = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $attributionNotRequired = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploader = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(enumType: ImageType::class)]
    private ?ImageType $type = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    private ?Event $event = null;

    #[ORM\Column(nullable: true, enumType: ImageReportReason::class)]
    private ?ImageReportReason $reported = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    /** Alt text for a locale, falling back to the base alt (a null locale returns the base alt directly). */
    public function getAltFor(?string $locale): ?string
    {
        if ($locale === null) {
            return $this->alt;
        }

        return $this->altTranslations[$locale] ?? $this->alt;
    }

    /**
     * Raw stored per-locale alt with no fallback (empty when unset) - for widget pre-fill.
     */
    public function getAltTranslation(string $locale): ?string
    {
        return $this->altTranslations[$locale] ?? null;
    }

    public function setAltTranslation(string $locale, ?string $value): static
    {
        $value = $value === null ? '' : trim($value);
        $map = $this->altTranslations ?? [];
        if ($value === '') {
            unset($map[$locale]);
        } else {
            $map[$locale] = $value;
        }
        $this->altTranslations = $map === [] ? null : $map;

        return $this;
    }

    /**
     * Enabled codes with no own alt yet - unlike getAltFor(), the base alt does not fill in for other
     * locales, so a locale counts as complete only when it has its own text.
     *
     * @param array<string> $codes
     * @return list<string>
     */
    public function missingAltLocales(array $codes, string $sourceLocale): array
    {
        $missing = [];
        foreach ($codes as $code) {
            $own = $code === $sourceLocale ? $this->alt : $this->altTranslations[$code] ?? null;
            if ($own === null || $own === '') {
                $missing[] = $code;
            }
        }

        return $missing;
    }

    public function getAttribution(): ?string
    {
        return $this->attribution;
    }

    public function setAttribution(?string $attribution): static
    {
        $this->attribution = $attribution;

        return $this;
    }

    public function isAttributionNotRequired(): bool
    {
        return $this->attributionNotRequired;
    }

    public function setAttributionNotRequired(bool $attributionNotRequired): static
    {
        $this->attributionNotRequired = $attributionNotRequired;

        return $this;
    }

    public function getAttributionStatus(): AttributionStatus
    {
        if ($this->attributionNotRequired) {
            return AttributionStatus::NotRequired;
        }

        if ($this->attribution !== null && $this->attribution !== '') {
            return AttributionStatus::Provided;
        }

        return AttributionStatus::Pending;
    }

    public function getUploader(): ?User
    {
        return $this->uploader;
    }

    public function setUploader(?User $uploader): static
    {
        $this->uploader = $uploader;

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

    public function getType(): ?ImageType
    {
        return $this->type;
    }

    public function setType(ImageType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getReported(): ?ImageReportReason
    {
        return $this->reported;
    }

    public function setReported(?ImageReportReason $reported): static
    {
        $this->reported = $reported;

        return $this;
    }
}
