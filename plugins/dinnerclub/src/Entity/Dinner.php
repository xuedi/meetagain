<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Plugin\Dinnerclub\Repository\DinnerRepository;

#[ORM\Entity(repositoryClass: DinnerRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_dinner_event', columns: ['event_id'])]
class Dinner
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?float $pricePerPerson = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reservationName = null;

    #[ORM\OneToMany(
        targetEntity: DinnerCourse::class,
        mappedBy: 'dinner',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
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

    public function getPricePerPerson(): ?float
    {
        return $this->pricePerPerson !== null ? (float) $this->pricePerPerson : null;
    }

    public function setPricePerPerson(?float $pricePerPerson): static
    {
        $this->pricePerPerson = $pricePerPerson;

        return $this;
    }

    public function getReservationName(): ?string
    {
        return $this->reservationName;
    }

    public function setReservationName(?string $reservationName): static
    {
        $this->reservationName = $reservationName;

        return $this;
    }

    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(DinnerCourse $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setDinner($this);
        }

        return $this;
    }

    public function removeCourse(DinnerCourse $course): static
    {
        $this->courses->removeElement($course);

        return $this;
    }
}
