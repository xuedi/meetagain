<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Activity\Messages\PasswordReset;
use App\Activity\Messages\PasswordResetRequest;
use App\Emails\Types\PasswordResetEmail;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Member\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetServiceTest extends TestCase
{
    private function createService(
        ?UserRepository $userRepo = null,
        ?EntityManagerInterface $em = null,
        ?UserPasswordHasherInterface $hasher = null,
        ?ActivityService $activityService = null,
        ?PasswordResetEmail $passwordResetEmail = null,
        ?BlocklistCheckerInterface $blocklist = null,
        ?LoggerInterface $logger = null,
    ): PasswordResetService {
        return new PasswordResetService(
            $userRepo ?? $this->createStub(UserRepository::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $hasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $activityService ?? $this->createStub(ActivityService::class),
            $passwordResetEmail ?? $this->createStub(PasswordResetEmail::class),
            $blocklist ?? $this->createStub(BlocklistCheckerInterface::class),
            $logger ?? new NullLogger(),
        );
    }

    public function testRequestResetReturnsNullWhenUserNotFound(): void
    {
        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->requestReset('unknown@example.com');

        static::assertNull($result);
    }

    public function testRequestResetSetsRegcodeAndPersistsUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($user);
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(userRepo: $userRepoStub, em: $emMock);

        $result = $service->requestReset('test@example.com');

        static::assertSame($user, $result);
        static::assertNotNull($user->getRegcode());
        static::assertSame(64, strlen($user->getRegcode())); // bin2hex(random_bytes(32)) is 64 chars
    }

    public function testRequestResetLogsActivityAndSendsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($user);

        $activityMock = $this->createMock(ActivityService::class);
        $activityMock->expects($this->once())->method('log')->with(PasswordResetRequest::TYPE, $user);

        $emailMock = $this->createMock(PasswordResetEmail::class);
        $emailMock->expects($this->once())->method('send')->with(['user' => $user]);

        $service = $this->createService(
            userRepo: $userRepoStub,
            activityService: $activityMock,
            passwordResetEmail: $emailMock,
        );

        $service->requestReset('test@example.com');
    }

    public function testFindUserByResetCodeReturnsUser(): void
    {
        $user = new User();
        $user->setRegcode('abc123');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($user);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->findUserByResetCode('abc123');

        static::assertSame($user, $result);
    }

    public function testFindUserByResetCodeReturnsNullWhenNotFound(): void
    {
        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->findUserByResetCode('invalid-code');

        static::assertNull($result);
    }

    public function testResetPasswordHashesPasswordAndClearsRegcode(): void
    {
        $user = new User();
        $user->setRegcode('abc123');
        $user->setPassword('old-hashed-password');

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newPassword123')
            ->willReturn('new-hashed-password');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($user);
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock, hasher: $hasherMock);

        static::assertTrue($service->resetPassword($user, 'newPassword123'));

        static::assertSame('new-hashed-password', $user->getPassword());
        static::assertNull($user->getRegcode());
    }

    public function testResetPasswordLogsActivity(): void
    {
        $user = new User();

        $hasherStub = $this->createStub(UserPasswordHasherInterface::class);
        $hasherStub->method('hashPassword')->willReturn('hashed');

        $activityMock = $this->createMock(ActivityService::class);
        $activityMock->expects($this->once())->method('log')->with(PasswordReset::TYPE, $user);

        $service = $this->createService(hasher: $hasherStub, activityService: $activityMock);

        static::assertTrue($service->resetPassword($user, 'newPassword'));
    }

    public function testRequestResetReturnsNullWhenEmailBlocklisted(): void
    {
        $blocklistStub = $this->createStub(BlocklistCheckerInterface::class);
        $blocklistStub->method('isBlocked')->willReturn(true);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->never())->method('findOneBy');

        $emailMock = $this->createMock(PasswordResetEmail::class);
        $emailMock->expects($this->never())->method('send');

        $service = $this->createService(
            userRepo: $userRepoMock,
            passwordResetEmail: $emailMock,
            blocklist: $blocklistStub,
        );

        static::assertNull($service->requestReset('blocked@example.com'));
    }

    public function testResetPasswordReturnsFalseAndDoesNotPersistWhenBlocklisted(): void
    {
        $user = new User();
        $user->setEmail('blocked@example.com');
        $user->setPassword('old-hashed-password');

        $blocklistStub = $this->createStub(BlocklistCheckerInterface::class);
        $blocklistStub->method('isBlocked')->willReturn(true);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $emMock->expects($this->never())->method('flush');

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock->expects($this->never())->method('hashPassword');

        $activityMock = $this->createMock(ActivityService::class);
        $activityMock->expects($this->never())->method('log');

        $service = $this->createService(
            em: $emMock,
            hasher: $hasherMock,
            activityService: $activityMock,
            blocklist: $blocklistStub,
        );

        static::assertFalse($service->resetPassword($user, 'newPassword'));
        static::assertSame('old-hashed-password', $user->getPassword());
    }
}
