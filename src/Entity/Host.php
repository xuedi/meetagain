<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\HostRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HostRepository::class)]
class Host
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\Column(length: 16)]
    private null|string $name = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private null|User $user = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getName(): null|string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
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
}
