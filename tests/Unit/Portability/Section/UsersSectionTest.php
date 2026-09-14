<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\UserRole;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\UsersSection;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;

final class UsersSectionTest extends SectionTestCase
{
    public function testAUserSurvivesTheRoundTrip(): void
    {
        // Arrange
        $source = $this->withId(new User(), 5);
        $source->setEmail('ada@example.org');
        $source->setName('Ada');
        $source->setLocale('de');
        $source->setBio('Plays go on Mondays');
        $source->setPublic(false);
        $source->setPassword('$2y$13$hash');
        $source->setImage(new Image());
        $exported = $this->section([$source])->export(new Scope(users: [5 => 'admin']), $this->images());
        $avatar = new Image();
        $context = $this->context($avatar);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $user = $this->onlyPersisted(User::class);
        static::assertSame('ada@example.org', $user->getEmail());
        static::assertSame('Ada', $user->getName());
        static::assertSame('de', $user->getLocale());
        static::assertSame('Plays go on Mondays', $user->getBio());
        static::assertFalse($user->isPublic());
        static::assertSame(UserRole::Admin, $user->getRole());
        static::assertSame($avatar, $user->getImage());
        static::assertSame('$2y$13$hash', $user->getPassword());
        static::assertSame($user, $context->resolveRef(User::class, 'ada@example.org'));
    }

    public function testARowWithoutAPasswordHashLocksTheAccount(): void
    {
        // Arrange
        $row = ['email' => 'member@example.org'];

        // Act
        $this->section()->import([$row], $this->context());

        // Assert
        static::assertSame('', $this->onlyPersisted(User::class)->getPassword());
    }

    /**
     * @return iterable<string, array{string, UserRole}>
     */
    public static function roleProvider(): iterable
    {
        yield 'an owner becomes an admin' => ['admin', UserRole::Admin];
        yield 'an organizer becomes an admin' => ['organizer', UserRole::Admin];
        yield 'a member stays a user' => ['user', UserRole::User];
    }

    #[DataProvider('roleProvider')]
    public function testTheArchiveRoleMapsToACoreRole(string $archiveRole, UserRole $expected): void
    {
        // Arrange
        $row = ['email' => 'member@example.org', 'role' => $archiveRole];

        // Act
        $this->section()->import([$row], $this->context());

        // Assert
        static::assertSame($expected, $this->onlyPersisted(User::class)->getRole());
    }

    public function testAKnownEmailIsMatchedToTheExistingUser(): void
    {
        // Arrange
        $existing = new User();
        $context = $this->context();

        // Act
        $this->section([], $existing)->import([['email' => 'ada@example.org']], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame($existing, $context->resolveRef(User::class, 'ada@example.org'));
        static::assertSame(1, $context->toSummary()->get('users', Outcome::Matched));
    }

    public function testSystemAccountsNeverTravel(): void
    {
        // Arrange
        $system = $this->withId(new User(), 5);
        $system->setEmail('import@example.com');
        $system->setRole(UserRole::System);

        // Act
        $rows = $this->section([$system])->export(new Scope(users: [5 => 'user']), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    public function testMembersAreExportedInScopeOrder(): void
    {
        // Arrange
        $first = $this->withId(new User(), 5);
        $first->setEmail('five@example.org');
        $second = $this->withId(new User(), 9);
        $second->setEmail('nine@example.org');

        // Act
        $rows = $this->section([$first, $second])->export(new Scope(users: [9 => 'user', 5 => 'admin']), $this->images());

        // Assert
        static::assertSame(['nine@example.org', 'five@example.org'], array_column($rows, 'email'));
    }

    /**
     * @param list<User> $found
     */
    private function section(array $found = [], ?User $existing = null): UsersSection
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findBy')->willReturn($found);
        $repository->method('findOneBy')->willReturn($existing);

        return new UsersSection($this->entityManager(), $repository);
    }
}
