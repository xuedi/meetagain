<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\EmailService;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetServiceTest extends TestCase
{
    private function createService(
        ?UserRepository $userRepo = null,
        ?EntityManagerInterface $em = null,
        ?UserPasswordHasherInterface $hasher = null,
        ?ActivityService $activityService = null,
        ?EmailService $emailService = null,
    ): PasswordResetService {
        return new PasswordResetService(
            $userRepo ?? $this->createStub(UserRepository::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $hasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $activityService ?? $this->createStub(ActivityService::class),
            $emailService ?? $this->createStub(EmailService::class),
        );
    }

    public function testRequestResetReturnsNullWhenUserNotFound(): void
    {
        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->requestReset('unknown@example.com');

        $this->assertNull($result);
    }

    public function testRequestResetSetsRegcodeAndPersistsUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->with(['email' => 'test@example.com'])->willReturn($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($user);
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(userRepo: $userRepoStub, em: $emMock);

        $result = $service->requestReset('test@example.com');

        $this->assertSame($user, $result);
        $this->assertNotNull($user->getRegcode());
        $this->assertEquals(40, strlen($user->getRegcode())); // SHA1 hash is 40 chars
    }

    public function testRequestResetLogsActivityAndSendsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($user);

        $activityMock = $this->createMock(ActivityService::class);
        $activityMock->expects($this->once())->method('log')
            ->with(ActivityType::PasswordResetRequest, $user);

        $emailMock = $this->createMock(EmailService::class);
        $emailMock->expects($this->once())->method('prepareResetPassword')->with($user);
        $emailMock->expects($this->once())->method('sendQueue');

        $service = $this->createService(
            userRepo: $userRepoStub,
            activityService: $activityMock,
            emailService: $emailMock,
        );

        $service->requestReset('test@example.com');
    }

    public function testFindUserByResetCodeReturnsUser(): void
    {
        $user = new User();
        $user->setRegcode('abc123');

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->with(['regcode' => 'abc123'])->willReturn($user);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->findUserByResetCode('abc123');

        $this->assertSame($user, $result);
    }

    public function testFindUserByResetCodeReturnsNullWhenNotFound(): void
    {
        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $service = $this->createService(userRepo: $userRepoStub);

        $result = $service->findUserByResetCode('invalid-code');

        $this->assertNull($result);
    }

    public function testResetPasswordHashesPasswordAndClearsRegcode(): void
    {
        $user = new User();
        $user->setRegcode('abc123');
        $user->setPassword('old-hashed-password');

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newPassword123')
            ->willReturn('new-hashed-password');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($user);
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock, hasher: $hasherMock);

        $service->resetPassword($user, 'newPassword123');

        $this->assertEquals('new-hashed-password', $user->getPassword());
        $this->assertNull($user->getRegcode());
    }

    public function testResetPasswordLogsActivity(): void
    {
        $user = new User();

        $hasherStub = $this->createStub(UserPasswordHasherInterface::class);
        $hasherStub->method('hashPassword')->willReturn('hashed');

        $activityMock = $this->createMock(ActivityService::class);
        $activityMock->expects($this->once())->method('log')
            ->with(ActivityType::PasswordReset, $user);

        $service = $this->createService(hasher: $hasherStub, activityService: $activityMock);

        $service->resetPassword($user, 'newPassword');
    }
}
