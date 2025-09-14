<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotFoundLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotFoundLogRepository::class)]
class NotFoundLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 255)]
    private null|string $url = null;

    #[ORM\Column]
    private null|\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 16)]
    private null|string $ip = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getUrl(): null|string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
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

    public function getIp(): null|string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }
}
