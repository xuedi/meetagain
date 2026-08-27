<?php declare(strict_types=1);

namespace Module\Trust\Internal\Entity;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\Repository\TrustGrantRepository;

#[ORM\Entity(repositoryClass: TrustGrantRepository::class)]
#[ORM\Table(name: 'mod_trust_grant')]
#[ORM\UniqueConstraint(name: 'uniq_trust_grant_edge', columns: ['context', 'from_user_id', 'to_user_id'])]
#[ORM\Index(name: 'idx_trust_grant_context', columns: ['context'])]
class TrustGrant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private string $context;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $fromUser;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $toUser;

    #[ORM\Column(length: 16, enumType: TrustLevel::class)]
    private TrustLevel $level;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $context, User $fromUser, User $toUser, TrustLevel $level, DateTimeImmutable $now)
    {
        $this->context = $context;
        $this->fromUser = $fromUser;
        $this->toUser = $toUser;
        $this->level = $level;
        $this->createdAt = $now;
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

    public function getFromUser(): User
    {
        return $this->fromUser;
    }

    public function getToUser(): User
    {
        return $this->toUser;
    }

    public function getLevel(): TrustLevel
    {
        return $this->level;
    }

    public function setLevel(TrustLevel $level, DateTimeImmutable $now): static
    {
        $this->level = $level;
        $this->updatedAt = $now;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
