<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeveloperAppStatus;
use App\Repository\DeveloperAppApplicationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeveloperAppApplicationRepository::class)]
#[ORM\Table(name: 'developer_app_application')]
#[ORM\Index(name: 'idx_developer_app_status_submitted', fields: ['status', 'submittedAt'])]
#[ORM\Index(name: 'idx_developer_app_user_submitted', fields: ['submittedBy', 'submittedAt'])]
#[ORM\Index(name: 'idx_developer_app_client_identifier', fields: ['clientIdentifier'])]
class DeveloperAppApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $submittedBy;

    #[ORM\Column(length: 80)]
    private string $appName;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homepageUrl = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Image $logoImage = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $redirectUris = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $requestedGrants = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $requestedScopes = ['api'];

    #[ORM\Column(enumType: DeveloperAppStatus::class)]
    private DeveloperAppStatus $status = DeveloperAppStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $submittedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $denyReason = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $clientIdentifier = null;

    #[ORM\Column]
    private bool $userReadOutcome = false;

    /**
     * @param list<string> $redirectUris
     * @param list<string> $requestedGrants
     */
    public function __construct(User $submittedBy, string $appName, array $redirectUris, array $requestedGrants)
    {
        $this->submittedBy = $submittedBy;
        $this->appName = $appName;
        $this->redirectUris = array_values($redirectUris);
        $this->requestedGrants = array_values($requestedGrants);
        $this->submittedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubmittedBy(): User
    {
        return $this->submittedBy;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function setAppName(string $appName): static
    {
        $this->appName = $appName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHomepageUrl(): ?string
    {
        return $this->homepageUrl;
    }

    public function setHomepageUrl(?string $homepageUrl): static
    {
        $this->homepageUrl = $homepageUrl;

        return $this;
    }

    public function getLogoImage(): ?Image
    {
        return $this->logoImage;
    }

    public function setLogoImage(?Image $logoImage): static
    {
        $this->logoImage = $logoImage;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * @param list<string> $redirectUris
     */
    public function setRedirectUris(array $redirectUris): static
    {
        $this->redirectUris = array_values($redirectUris);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRequestedGrants(): array
    {
        return $this->requestedGrants;
    }

    /**
     * @param list<string> $requestedGrants
     */
    public function setRequestedGrants(array $requestedGrants): static
    {
        $this->requestedGrants = array_values($requestedGrants);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRequestedScopes(): array
    {
        return $this->requestedScopes;
    }

    /**
     * @param list<string> $requestedScopes
     */
    public function setRequestedScopes(array $requestedScopes): static
    {
        $this->requestedScopes = array_values($requestedScopes);

        return $this;
    }

    public function getStatus(): DeveloperAppStatus
    {
        return $this->status;
    }

    public function setStatus(DeveloperAppStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getDenyReason(): ?string
    {
        return $this->denyReason;
    }

    public function setDenyReason(?string $denyReason): static
    {
        $this->denyReason = $denyReason;

        return $this;
    }

    public function getClientIdentifier(): ?string
    {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier(?string $clientIdentifier): static
    {
        $this->clientIdentifier = $clientIdentifier;

        return $this;
    }

    public function isUserReadOutcome(): bool
    {
        return $this->userReadOutcome;
    }

    public function setUserReadOutcome(bool $userReadOutcome): static
    {
        $this->userReadOutcome = $userReadOutcome;

        return $this;
    }

    public function hasUnreadOutcome(): bool
    {
        return !$this->userReadOutcome
            && in_array($this->status, [
                DeveloperAppStatus::Approved,
                DeveloperAppStatus::Denied,
                DeveloperAppStatus::Revoked,
            ], true);
    }

    public function markOutcomeRead(): void
    {
        $this->userReadOutcome = true;
    }
}
