<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountDeletionReason;
use App\Repository\AccountDeletionFeedbackRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountDeletionFeedbackRepository::class)]
class AccountDeletionFeedback
{
    public const int MAX_COMMENT_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, enumType: AccountDeletionReason::class)]
    private AccountDeletionReason $reason;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(AccountDeletionReason $reason, ?string $comment, string $locale, DateTimeImmutable $createdAt)
    {
        $this->reason = $reason;
        $this->comment = $comment;
        $this->locale = $locale;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReason(): AccountDeletionReason
    {
        return $this->reason;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
