<?php declare(strict_types=1);

namespace Module\Trust\Internal\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;

#[ORM\Entity(repositoryClass: TrustContextConfigRepository::class)]
#[ORM\Table(name: 'mod_trust_context_config')]
class TrustContextConfig
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191, unique: true)]
    private string $context;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $context, array $payload, DateTimeImmutable $now)
    {
        $this->context = $context;
        $this->payload = $payload;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload, DateTimeImmutable $now): static
    {
        $this->payload = $payload;
        $this->updatedAt = $now;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
