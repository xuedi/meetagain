<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RsvpGuest
{
    public const int MAX_GUESTS = 5;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $guests = 0;

    public function __construct(Event $event, User $user)
    {
        $this->event = $event;
        $this->user = $user;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGuests(): int
    {
        return $this->guests;
    }

    public function increment(): bool
    {
        if ($this->guests >= self::MAX_GUESTS) {
            return false;
        }
        $this->guests++;

        return true;
    }

    public function decrement(): bool
    {
        if ($this->guests <= 0) {
            return false;
        }
        $this->guests--;

        return true;
    }
}
