<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\SupportAudience;
use App\Enum\SupportChannel;
use App\Enum\SupportRequestStatus;
use App\Repository\SupportRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SensitiveParameter;

#[ORM\Entity(repositoryClass: SupportRequestRepository::class)]
class SupportRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $requester = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 10, enumType: SupportRequestStatus::class)]
    private SupportRequestStatus $status = SupportRequestStatus::New;

    #[ORM\Column(length: 10, enumType: SupportAudience::class)]
    private SupportAudience $audience = SupportAudience::Organizer;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $respondedBy = null;

    #[ORM\Column(length: 10, enumType: SupportChannel::class)]
    private SupportChannel $channel = SupportChannel::Thread;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $emailVerifyToken = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $emailVerifyExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $invitedAdminsAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedAdminsBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStatus(): SupportRequestStatus
    {
        return $this->status;
    }

    public function setStatus(SupportRequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAudience(): SupportAudience
    {
        return $this->audience;
    }

    public function setAudience(SupportAudience $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getRespondedBy(): ?User
    {
        return $this->respondedBy;
    }

    public function setRespondedBy(?User $respondedBy): static
    {
        $this->respondedBy = $respondedBy;

        return $this;
    }

    public function getChannel(): SupportChannel
    {
        return $this->channel;
    }

    public function setChannel(SupportChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(#[SensitiveParameter] ?string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getLastActivityAt(): ?DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

        return $this;
    }

    public function getEmailVerifyToken(): ?string
    {
        return $this->emailVerifyToken;
    }

    public function setEmailVerifyToken(#[SensitiveParameter] ?string $emailVerifyToken): static
    {
        $this->emailVerifyToken = $emailVerifyToken;

        return $this;
    }

    public function getEmailVerifyExpiresAt(): ?DateTimeImmutable
    {
        return $this->emailVerifyExpiresAt;
    }

    public function setEmailVerifyExpiresAt(?DateTimeImmutable $emailVerifyExpiresAt): static
    {
        $this->emailVerifyExpiresAt = $emailVerifyExpiresAt;

        return $this;
    }

    public function getInvitedAdminsAt(): ?DateTimeImmutable
    {
        return $this->invitedAdminsAt;
    }

    public function setInvitedAdminsAt(?DateTimeImmutable $invitedAdminsAt): static
    {
        $this->invitedAdminsAt = $invitedAdminsAt;

        return $this;
    }

    public function getInvitedAdminsBy(): ?User
    {
        return $this->invitedAdminsBy;
    }

    public function setInvitedAdminsBy(?User $invitedAdminsBy): static
    {
        $this->invitedAdminsBy = $invitedAdminsBy;

        return $this;
    }

    public function hasInvitedAdmins(): bool
    {
        return $this->invitedAdminsAt instanceof DateTimeImmutable;
    }

    public function canInviteAdmins(): bool
    {
        return $this->audience === SupportAudience::Organizer && !$this->hasInvitedAdmins();
    }

    public function isNew(): bool
    {
        return $this->status === SupportRequestStatus::New;
    }

    public function isRead(): bool
    {
        return $this->status === SupportRequestStatus::Read;
    }

    public function isReplied(): bool
    {
        return $this->status === SupportRequestStatus::Replied;
    }

    public function isReopened(): bool
    {
        return $this->status === SupportRequestStatus::Reopened;
    }

    public function isResolved(): bool
    {
        return $this->status === SupportRequestStatus::Resolved;
    }

    public function isOpenForRequester(): bool
    {
        return $this->status !== SupportRequestStatus::Resolved;
    }

    public function getRequesterLabel(): ?string
    {
        return $this->requester?->getName() ?? $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof DateTimeImmutable && $this->email !== null;
    }
}
