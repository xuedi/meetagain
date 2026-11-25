<?php declare(strict_types=1);

namespace Plugin\Fediverse\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Fediverse\Repository\AccountRepository;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'plg_fediverse_account')]
#[ORM\UniqueConstraint(name: 'uniq_fediverse_account_username', columns: ['username'])]
#[ORM\Index(name: 'idx_fediverse_account_scope', columns: ['scope_id'])]
class Account
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $scopeId = null;

    #[ORM\Column(length: 30)]
    private string $username;

    #[ORM\Column(type: Types::TEXT)]
    private string $publicKey;

    #[ORM\Column(type: Types::TEXT)]
    private string $encryptedPrivateKey;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lockedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $retiredAt = null;

    public function __construct(?string $scopeId, string $username, string $publicKey, string $encryptedPrivateKey, DateTimeImmutable $createdAt)
    {
        $this->scopeId = $scopeId;
        $this->username = $username;
        $this->publicKey = $publicKey;
        $this->encryptedPrivateKey = $encryptedPrivateKey;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScopeId(): ?string
    {
        return $this->scopeId;
    }

    public function isInstallation(): bool
    {
        return $this->scopeId === null;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getEncryptedPrivateKey(): string
    {
        return $this->encryptedPrivateKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLockedAt(): ?DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function isLocked(): bool
    {
        return $this->lockedAt !== null;
    }

    public function lock(DateTimeImmutable $at): static
    {
        $this->lockedAt ??= $at;

        return $this;
    }

    public function getRetiredAt(): ?DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function isRetired(): bool
    {
        return $this->retiredAt !== null;
    }

    public function retire(DateTimeImmutable $at): static
    {
        $this->retiredAt ??= $at;

        return $this;
    }
}
