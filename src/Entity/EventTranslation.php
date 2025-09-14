<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(fields: ['language', 'event'])]
#[ORM\Entity(repositoryClass: EventTranslationRepository::class)]
class EventTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private null|int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private null|Event $event = null;

    #[ORM\Column(length: 2)]
    private null|string $language = null;

    #[ORM\Column(length: 255)]
    private null|string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private null|string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private null|string $teaser = null;

    public function getId(): null|int
    {
        return $this->id;
    }

    public function getEvent(): null|Event
    {
        return $this->event;
    }

    public function setEvent(null|Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getLanguage(): null|string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTitle(): null|string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTeaser(): null|string
    {
        return $this->teaser;
    }

    public function setTeaser(null|string $teaser): static
    {
        $this->teaser = $teaser;

        return $this;
    }
}
