<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 128)]
    private null|string $mimeType = null;

    #[ORM\Column(length: 8)]
    private null|string $extension = null;

    #[ORM\Column(type: Types::BIGINT)]
    private null|int $size = null;

    #[ORM\Column(length: 64, unique: true)]
    private null|string $hash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private null|string $alt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $uploader = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private null|DateTimeImmutable $updatedAt = null;

    #[ORM\Column(enumType: ImageType::class)]
    private null|ImageType $type = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    private null|Event $event = null;

    #[ORM\Column(nullable: true, enumType: ImageReported::class)]
    private null|ImageReported $reported = null;

    public function getId(): null|int
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

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getAlt(): null|string
    {
        return $this->alt;
    }

    public function setAlt(null|string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getUploader(): null|User
    {
        return $this->uploader;
    }

    public function setUploader(null|User $uploader): static
    {
        $this->uploader = $uploader;

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

    public function getType(): null|ImageType
    {
        return $this->type;
    }

    public function setType(ImageType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUpdatedAt(): null|DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(null|DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEvent(): null|Event
    {
        return $this->event;
    }

    public function setEvent(null|Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getReported(): null|ImageReported
    {
        return $this->reported;
    }

    public function setReported(null|ImageReported $reported): static
    {
        $this->reported = $reported;

        return $this;
    }
}
