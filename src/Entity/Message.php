<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column]
    private null|\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'messagesSend')]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'messagesReceived')]
    #[ORM\JoinColumn(nullable: false)]
    private null|User $receiver = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $content = null;

    #[ORM\Column]
    private null|bool $deleted = null;

    #[ORM\Column]
    private null|bool $wasRead = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getCreatedAt(): null|\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSender(): null|User
    {
        return $this->sender;
    }

    public function setSender(null|User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getReceiver(): null|User
    {
        return $this->receiver;
    }

    public function setReceiver(null|User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getContent(): null|string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function isDeleted(): null|bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function isWasRead(): null|bool
    {
        return $this->wasRead;
    }

    public function setWasRead(bool $wasRead): static
    {
        $this->wasRead = $wasRead;

        return $this;
    }
}
