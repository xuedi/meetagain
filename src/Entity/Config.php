<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 128)]
    private ?string $value = null;

    #[ORM\Column(enumType: ConfigType::class)]
    private ?ConfigType $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getType(): ?ConfigType
    {
        return $this->type;
    }

    public function setType(ConfigType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isBoolean(): bool
    {
        return $this->type === ConfigType::Boolean;
    }

    public function getBoolean(): bool
    {
        if (!$this->isBoolean()) {
            throw new LogicException('Config is not boolean');
        }

        return $this->getValue() === 'true';
    }
}
