<?php declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailType;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_email_queue_status', columns: ['status'])]
class EmailQueue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTime $sendAt = null;

    #[ORM\Column(length: 20, enumType: EmailQueueStatus::class)]
    private EmailQueueStatus $status = EmailQueueStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(length: 255)]
    private ?string $sender = null;

    #[ORM\Column(length: 255)]
    private ?string $recipient = null;

    #[ORM\Column(length: 2)]
    private ?string $lang = null;

    #[ORM\Column]
    private array $context = [];

    #[ORM\Column(length: 64, nullable: true, enumType: EmailType::class)]
    private ?EmailType $template = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $renderedBody = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSendAt(): ?DateTime
    {
        return $this->sendAt;
    }

    public function setSendAt(?DateTime $sendAt): static
    {
        $this->sendAt = $sendAt;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(string $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(string $lang): static
    {
        $this->lang = $lang;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getTemplate(): ?EmailType
    {
        return $this->template;
    }

    public function setTemplate(?EmailType $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getRenderedBody(): ?string
    {
        return $this->renderedBody;
    }

    public function setRenderedBody(?string $renderedBody): static
    {
        $this->renderedBody = $renderedBody;

        return $this;
    }

    public function getStatus(): EmailQueueStatus
    {
        return $this->status;
    }

    public function setStatus(EmailQueueStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === EmailQueueStatus::Pending;
    }

    public function isSent(): bool
    {
        return $this->status === EmailQueueStatus::Sent;
    }

    public function isFailed(): bool
    {
        return $this->status === EmailQueueStatus::Failed;
    }
}
