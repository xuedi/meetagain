<?php

namespace App\Entity;

use App\Repository\EmailQueueRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailQueueRepository::class)]
class EmailQueue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private null|DateTime $sendAt = null;

    #[ORM\Column(length: 255)]
    private null|string $subject = null;

    #[ORM\Column(length: 255)]
    private null|string $sender = null;

    #[ORM\Column(length: 255)]
    private null|string $recipient = null;

    #[ORM\Column(length: 2)]
    private null|string $lang = null;

    #[ORM\Column]
    private array $context = [];

    #[ORM\Column(length: 255)]
    private null|string $template = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getCreatedAt(): null|DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSendAt(): null|DateTime
    {
        return $this->sendAt;
    }

    public function setSendAt(null|DateTime $sendAt): static
    {
        $this->sendAt = $sendAt;

        return $this;
    }

    public function getSubject(): null|string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSender(): null|string
    {
        return $this->sender;
    }

    public function setSender(string $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): null|string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getLang(): null|string
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

    public function getTemplate(): null|string
    {
        return $this->template;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }
}
