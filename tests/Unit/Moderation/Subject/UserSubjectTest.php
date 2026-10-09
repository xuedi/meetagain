<?php declare(strict_types=1);

namespace Tests\Unit\Moderation\Subject;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Member\MemberFilterService;
use App\Moderation\Subject\UserSubject;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Unit\Stubs\UserStub;

class UserSubjectTest extends TestCase
{
    public function testDescribeSnapshotsNameAndBio(): void
    {
        // Arrange
        $target = $this->makeUser(2)->setName('Orlando')->setBio('  Selling watches  ');
        $subject = $this->makeSubject($target, accessible: true);

        // Act
        $snapshot = $subject->describe(2, $this->makeUser(1));

        // Assert
        static::assertNotNull($snapshot);
        static::assertSame('Orlando', $snapshot->label);
        static::assertSame('Selling watches', $snapshot->excerpt);
        static::assertSame($target, $snapshot->author);
    }

    #[DataProvider('provideRefusedCases')]
    public function testDescribeRefuses(?User $target, bool $accessible): void
    {
        // Arrange
        $subject = $this->makeSubject($target, $accessible);

        // Act
        $snapshot = $subject->describe(2, $this->makeUser(1));

        // Assert
        static::assertNull($snapshot);
    }

    public static function provideRefusedCases(): iterable
    {
        yield 'missing member' => [null, true];
        yield 'reporting yourself' => [new UserStub()->setId(1), true];
        yield 'deleted member' => [
            new UserStub()
                ->setId(2)
                ->setStatus(UserStatus::Deleted),
            true,
        ];
        yield 'system member' => [
            new UserStub()
                ->setId(2)
                ->setStatus(UserStatus::Active)
                ->setRole(UserRole::System),
            true,
        ];
        yield 'member hidden by the filter chain' => [
            new UserStub()
                ->setId(2)
                ->setStatus(UserStatus::Active),
            false,
        ];
    }

    private function makeSubject(?User $target, bool $accessible): UserSubject
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('find')->willReturn($target);
        $filter = $this->createStub(MemberFilterService::class);
        $filter->method('isMemberAccessible')->willReturn($accessible);

        return new UserSubject($users, $filter, $this->createStub(UrlGeneratorInterface::class));
    }

    private function makeUser(int $id): UserStub
    {
        return new UserStub()
            ->setId($id)
            ->setStatus(UserStatus::Active)
            ->setRole(UserRole::User);
    }
}
