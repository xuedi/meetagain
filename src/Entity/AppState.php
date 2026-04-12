<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppStateRepository::class)]
class AppState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'key_name', length: 255, unique: true)]
    private string $keyName;

    #[ORM\Column(type: 'text')]
    private string $value;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $keyName, string $value, DateTimeImmutable $updatedAt)
    {
        $this->keyName = $keyName;
        $this->value = $value;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
