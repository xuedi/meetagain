<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\EventInterval;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EventSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'integer', nullable: true, enumType: EventInterval::class)]
    private ?EventInterval $rule = null;

    // Authoritative only when the rule is EventInterval::Custom; an RFC-5545 rule string.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ruleSpec = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRule(): ?EventInterval
    {
        return $this->rule;
    }

    public function setRule(?EventInterval $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function getRuleSpec(): ?string
    {
        return $this->ruleSpec;
    }

    public function setRuleSpec(?string $ruleSpec): static
    {
        $this->ruleSpec = $ruleSpec;

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
}
