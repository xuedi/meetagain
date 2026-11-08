<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Enum\ImageType;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Portability\Cities;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PurgeContext;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class UsersSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private Cities $cities,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'users';
    }

    #[Override]
    public function getOrder(): int
    {
        return 20;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->users === []) {
            return [];
        }

        $usersById = [];
        foreach ($this->userRepository->findBy(['id' => array_keys($scope->users)]) as $user) {
            $usersById[(int) $user->getId()] = $user;
        }

        $rows = [];
        foreach ($scope->users as $userId => $role) {
            $user = $usersById[$userId] ?? null;
            if (!$user instanceof User || $user->getRole() === UserRole::System) {
                continue;
            }

            $rows[] = [
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'locale' => $user->getLocale(),
                'role' => $role,
                'password' => $user->getPassword(),
                'bio' => $user->getBio(),
                'public' => $user->isPublic(),
                'city_geoname_id' => $this->cities->geonameIdOf($user->getCityId()),
                'image_file' => $user->getImage() instanceof Image ? $images->addImage($user->getImage()) : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $email = (string) ($row['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $context->count($this->getKey(), Outcome::Matched);
            } else {
                $user = $this->createUser($email, $row, $context);
                $context->count($this->getKey(), Outcome::Created);
            }

            $context->mapRef(User::class, $email, $user);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function createUser(string $email, array $row, ImportContext $context): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName((string) ($row['name'] ?? ''));
        $user->setLocale((string) ($row['locale'] ?? 'en'));
        $user->setRole(match ($row['role'] ?? 'user') {
            'admin', 'organizer' => UserRole::Admin,
            default => UserRole::User,
        });
        $user->setPassword((string) ($row['password'] ?? ''));
        $user->setBio(isset($row['bio']) ? (string) $row['bio'] : null);
        $user->setPublic((bool) ($row['public'] ?? true));
        $user->setCityId($this->cities->resolve($row, $context, Cities::USER_KIND));
        $user->setStatus(UserStatus::Active);
        $user->setVerified(true);
        $user->setRestricted(false);
        $user->setTagging(false);
        $user->setOsmConsent(false);
        $user->setNotification(false);
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setLastLogin(new DateTime());

        $image = $context->importImage($row['image_file'] ?? null, ImageType::ProfilePicture);
        if ($image instanceof Image) {
            $user->setImage($image);
        }

        $this->em->persist($user);

        return $user;
    }

    #[Override]
    public function purge(PurgeContext $context): void
    {
        $spared = array_values(array_unique([(int) $context->admin->getId(), (int) $context->systemUser->getId(), ...$this->systemAccountIds()]));
        foreach ([Image::class => 'uploader', Location::class => 'user'] as $class => $field) {
            $this->em
                ->createQuery(sprintf('UPDATE %1$s e SET e.%2$s = :system WHERE e.%2$s NOT IN (:spared)', $class, $field))
                ->execute(['system' => $context->systemUser, 'spared' => $spared]);
        }

        $this->em
            ->createQuery(sprintf('DELETE %s m WHERE m.sender NOT IN (:spared) OR m.receiver NOT IN (:spared)', Message::class))
            ->execute(['spared' => $spared]);
        $this->em
            ->createQuery(sprintf('DELETE %s b WHERE b.blocker NOT IN (:spared) OR b.blocked NOT IN (:spared)', UserBlock::class))
            ->execute(['spared' => $spared]);
        $this->em->createQuery(sprintf('DELETE %s a WHERE a.user NOT IN (:spared)', Activity::class))->execute(['spared' => $spared]);
        $this->em->createQuery(sprintf('DELETE %s u WHERE u.id NOT IN (:spared)', User::class))->execute(['spared' => $spared]);
    }

    /**
     * @return list<int>
     */
    private function systemAccountIds(): array
    {
        return array_map(static fn(User $user): int => (int) $user->getId(), $this->userRepository->findBy(['role' => UserRole::System]));
    }
}
