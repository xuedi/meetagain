<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    private null|User $user = null;

    private null|string $message = null;

    #[ORM\Column]
    private null|DateTimeImmutable $createdAt = null;

    #[ORM\Column(enumType: ActivityType::class)]
    private null|ActivityType $type = null;

    #[ORM\Column(nullable: true)]
    private null|array $Meta = null; // TODO: do lower case

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getUser(): null|User
    {
        return $this->user;
    }

    public function setUser(null|User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMessage(): null|string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
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

    public function getType(): null|ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMeta(): null|array
    {
        return $this->Meta;
    }

    public function setMeta(null|array $Meta): static
    {
        $this->Meta = $Meta;

        return $this;
    }
}
