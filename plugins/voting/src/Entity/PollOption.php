<?php declare(strict_types=1);

namespace Plugin\Voting\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\Voting\Repository\PollOptionRepository;

/**
 * One candidate item on a poll's ballot. itemId is a plain INT keyed by the poll's itemType
 * (no FK to any plugin entity), so a poll of any item type stores candidates the same way.
 */
#[ORM\Entity(repositoryClass: PollOptionRepository::class)]
#[ORM\Table(name: 'plg_voting_poll_option')]
#[ORM\UniqueConstraint(name: 'uniq_poll_option_poll_item', columns: ['poll_id', 'item_id'])]
class PollOption
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\Column]
    private ?int $itemId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(Poll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }
}
