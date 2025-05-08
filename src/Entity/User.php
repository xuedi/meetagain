<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $lastLogin = null;

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'rsvp')]
    private Collection $rsvp;

    #[ORM\Column(length: 2)]
    private string $locale = 'en';

    #[ORM\Column(enumType: UserStatus::class)]
    private ?UserStatus $status = null;

    #[ORM\Column]
    private bool $public = true;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $regcode = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Image $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column]
    private bool $verified = false;

    #[ORM\Column]
    private ?bool $restricted = false;

    #[ORM\Column]
    private ?bool $osmConsent = false;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'user')]
    private Collection $activities;

    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'followers')]
    private Collection $following;

    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'following')]
    private Collection $followers;

    public function __construct()
    {
        $this->rsvp = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->following = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[CodeCoverageIgnore] #[\Override]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRsvpEvents(): Collection
    {
        return $this->rsvp;
    }

    public function addRsvpEvent(Event $event): static
    {
        if (!$this->rsvp->contains($event)) {
            $this->rsvp->add($event);
            $event->addRsvp($this);
        }

        return $this;
    }

    public function removeRsvpEvent(Event $event): static
    {
        if ($this->rsvp->removeElement($event)) {
            $event->removeRsvp($this);
        }

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;

        return $this;
    }

    public function getRegcode(): ?string
    {
        return $this->regcode;
    }

    public function setRegcode(?string $regcode): static
    {
        $this->regcode = $regcode;

        return $this;
    }

    public function getImage(): ?Image
    {
        return $this->image;
    }

    public function setImage(?Image $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setUser($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        // set the owning side to null (unless already changed)
        if ($this->activities->removeElement($activity) && $activity->getUser() === $this) {
            $activity->setUser(null);
        }

        return $this;
    }

    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(User $user): void
    {
        if ($this->getId() === $user->getId()) {
            return;
        }
        if ($this->followers->contains($user)) {
            return;
        }
        $this->followers->add($user);
    }

    public function removeFollower(User $user): void
    {
        if ($this->followers->contains($user)) {
            $this->followers->removeElement($user);
        }
    }

    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function addFollowing(User $user): void
    {
        if ($this->getId() === $user->getId()) {
            return;
        }
        if ($this->following->contains($user)) {
            return;
        }
        $this->following->add($user);
    }

    public function removeFollowing(User $user): void
    {
        if ($this->following->contains($user)) {
            $this->following->removeElement($user);
        }
    }

    public function isRestricted(): ?bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): static
    {
        $this->restricted = $restricted;

        return $this;
    }

    public function isOsmConsent(): ?bool
    {
        return $this->osmConsent;
    }

    public function setOsmConsent(bool $osmConsent): static
    {
        $this->osmConsent = $osmConsent;

        return $this;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }
}
