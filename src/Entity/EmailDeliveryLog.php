<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailDeliveryLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailDeliveryLogRepository::class)]
#[ORM\Index(columns: ['sent_at'], name: 'idx_email_delivery_log_sent')]
#[ORM\Index(columns: ['status'], name: 'idx_email_delivery_log_status')]
class EmailDeliveryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EmailQueue $emailQueue = null;

    #[ORM\Column]
    private ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(length: 20, enumType: EmailDeliveryStatus::class)]
    private EmailDeliveryStatus $status = EmailDeliveryStatus::Sent;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailQueue(): ?EmailQueue
    {
        return $this->emailQueue;
    }

    public function setEmailQueue(EmailQueue $emailQueue): static
    {
        $this->emailQueue = $emailQueue;

        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getStatus(): EmailDeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(EmailDeliveryStatus $status): static
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

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): static
    {
        $this->messageId = $messageId;

        return $this;
    }
}
