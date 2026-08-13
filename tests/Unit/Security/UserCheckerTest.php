<?php declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\MessageRepository;
use App\Security\UserChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    public function testCheckPreAuthSkipsNonUserObjects(): void
    {
        // Arrange
        $nonUserObject = $this->createStub(UserInterface::class);

        $subject = $this->createSubject();

        // Act & Assert
        $subject->checkPreAuth($nonUserObject);
        static::assertTrue(true);
    }

    public function testCheckPreAuthAllowsActiveUsers(): void
    {
        // Arrange
        $activeUser = $this->createStub(User::class);
        $activeUser->method('getStatus')->willReturn(UserStatus::Active);

        $subject = $this->createSubject();

        // Act & Assert
        $subject->checkPreAuth($activeUser);
        static::assertTrue(true);
    }

    #[DataProvider('inactiveStatusProvider')]
    public function testCheckPreAuthNeverRevealsStatusBeforeCredentialsAreVerified(UserStatus $status): void
    {
        // Arrange
        $inactiveUser = $this->createStub(User::class);
        $inactiveUser->method('getStatus')->willReturn($status);

        $subject = $this->createSubject();

        // Act & Assert
        $subject->checkPreAuth($inactiveUser);
        static::assertTrue(true);
    }

    #[DataProvider('statusMessageProvider')]
    public function testCheckPostAuthRejectsInactiveUsersWithItsOwnMessage(UserStatus $status, string $expected): void
    {
        // Arrange
        $inactiveUser = $this->createStub(User::class);
        $inactiveUser->method('getStatus')->willReturn($status);

        $subject = $this->createSubject();

        // Assert
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage($expected);

        // Act
        $subject->checkPostAuth($inactiveUser);
    }

    public static function inactiveStatusProvider(): iterable
    {
        yield 'awaiting email confirmation' => [UserStatus::Registered];
        yield 'awaiting admin approval' => [UserStatus::EmailVerified];
        yield 'blocked' => [UserStatus::Blocked];
        yield 'deleted' => [UserStatus::Deleted];
        yield 'denied' => [UserStatus::Denied];
    }

    public static function statusMessageProvider(): iterable
    {
        yield 'awaiting email confirmation' => [UserStatus::Registered, 'security.account_status_registered'];
        yield 'awaiting admin approval' => [UserStatus::EmailVerified, 'security.account_status_email_verified'];
        yield 'blocked' => [UserStatus::Blocked, 'security.account_status_blocked'];
        yield 'denied' => [UserStatus::Denied, 'security.account_status_denied'];
        yield 'deleted falls back to the generic message' => [UserStatus::Deleted, 'security.account_status_inactive'];
    }

    public function testCheckPostAuthSkipsNonUserObjects(): void
    {
        // Arrange
        $nonUserObject = $this->createStub(UserInterface::class);

        $subject = $this->createSubject();

        // Act & Assert
        $subject->checkPostAuth($nonUserObject);
        static::assertTrue(true);
    }

    public function testCheckPostAuthSkipsWhenNoRequest(): void
    {
        // Arrange
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert
        $subject->checkPostAuth($this->createActiveUser());
        static::assertTrue(true);
    }

    public function testCheckPostAuthUpdatesUserLoginAndLogsActivity(): void
    {
        // Arrange
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key) {
                $this->assertContains($key, ['lastLogin', 'hasNewMessage']);
            });

        $request = new Request();
        $request->setSession($sessionMock);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($request);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with(static::isInstanceOf(User::class));
        $emMock->expects($this->once())->method('flush');

        // Arrange
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock->expects($this->once())->method('log');

        // Arrange
        $msgRepoMock = $this->createMock(MessageRepository::class);
        $msgRepoMock->expects($this->once())->method('hasNewMessages')->willReturn(true);

        $subject = $this->createSubject(activityService: $activityServiceMock, em: $emMock, requestStack: $requestStackStub, msgRepo: $msgRepoMock);

        // Act
        $subject->checkPostAuth($this->createActiveUser());
    }

    public function testCheckPostAuthDoesNotSetNewMessageFlagWhenNoNewMessages(): void
    {
        // Arrange
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('set')->with('lastLogin', static::anything());

        $request = new Request();
        $request->setSession($sessionMock);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($request);

        // Arrange
        $msgRepoStub = $this->createStub(MessageRepository::class);
        $msgRepoStub->method('hasNewMessages')->willReturn(false);

        $subject = $this->createSubject(requestStack: $requestStackStub, msgRepo: $msgRepoStub);

        // Act
        $subject->checkPostAuth($this->createActiveUser());
    }

    private function createActiveUser(): User
    {
        $user = $this->createStub(User::class);
        $user->method('getStatus')->willReturn(UserStatus::Active);

        return $user;
    }

    private function createSubject(
        ?ActivityService $activityService = null,
        ?EntityManagerInterface $em = null,
        ?RequestStack $requestStack = null,
        ?MessageRepository $msgRepo = null,
    ): UserChecker {
        return new UserChecker(
            activityService: $activityService ?? $this->createStub(ActivityService::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            requestStack: $requestStack ?? $this->createStub(RequestStack::class),
            msgRepo: $msgRepo ?? $this->createStub(MessageRepository::class),
        );
    }
}
