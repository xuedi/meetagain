<?php declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Repository\MessageRepository;
use App\Security\UserChecker;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
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
        // Arrange: create non-User object implementing UserInterface
        $nonUserObject = $this->createStub(UserInterface::class);

        $subject = $this->createSubject();

        // Act & Assert: should complete without exception for non-User objects
        $subject->checkPreAuth($nonUserObject);
        $this->assertTrue(true);
    }

    public function testCheckPreAuthAllowsActiveUsers(): void
    {
        // Arrange: create active user
        $activeUser = $this->createStub(User::class);
        $activeUser->method('getStatus')->willReturn(UserStatus::Active);

        $subject = $this->createSubject();

        // Act & Assert: should complete without exception for active users
        $subject->checkPreAuth($activeUser);
        $this->assertTrue(true);
    }

    public function testCheckPreAuthThrowsExceptionForNonActiveUsers(): void
    {
        // Arrange: create user with non-active status (Registered)
        $nonActiveUser = $this->createStub(User::class);
        $nonActiveUser->method('getStatus')->willReturn(UserStatus::Registered);

        $subject = $this->createSubject();

        // Assert: expect exception with specific message
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('The user is not anymore or not jet active');

        // Act: check pre-auth for non-active user
        $subject->checkPreAuth($nonActiveUser);
    }

    public function testCheckPostAuthSkipsNonUserObjects(): void
    {
        // Arrange: create non-User object implementing UserInterface
        $nonUserObject = $this->createStub(UserInterface::class);

        $subject = $this->createSubject();

        // Act & Assert: should complete without exception for non-User objects
        $subject->checkPostAuth($nonUserObject);
        $this->assertTrue(true);
    }

    public function testCheckPostAuthSkipsWhenNoRequest(): void
    {
        // Arrange: request stack returns null
        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn(null);

        $subject = $this->createSubject(requestStack: $requestStackStub);

        // Act & Assert: should complete without exception when no request
        $subject->checkPostAuth($this->createStub(User::class));
        $this->assertTrue(true);
    }

    public function testCheckPostAuthUpdatesUserLoginAndLogsActivity(): void
    {
        // Arrange: mock session to verify lastLogin and hasNewMessage are set
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key) {
                $this->assertContains($key, ['lastLogin', 'hasNewMessage']);
            });

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionMock);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        // Arrange: mock entity manager to verify user is persisted and flushed
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $emMock->expects($this->once())->method('flush');

        // Arrange: mock activity service to verify login is logged
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock->expects($this->once())->method('log');

        // Arrange: mock message repository to return true for new messages
        $msgRepoMock = $this->createMock(MessageRepository::class);
        $msgRepoMock->expects($this->once())->method('hasNewMessages')->willReturn(true);

        $subject = $this->createSubject(
            activityService: $activityServiceMock,
            em: $emMock,
            requestStack: $requestStackStub,
            msgRepo: $msgRepoMock,
        );

        // Act: check post-auth
        $subject->checkPostAuth($this->createStub(User::class));
    }

    public function testCheckPostAuthDoesNotSetNewMessageFlagWhenNoNewMessages(): void
    {
        // Arrange: mock session to verify only lastLogin is set (not hasNewMessage)
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->once())
            ->method('set')
            ->with('lastLogin', $this->anything());

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getSession')->willReturn($sessionMock);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        // Arrange: mock message repository to return false for new messages
        $msgRepoStub = $this->createStub(MessageRepository::class);
        $msgRepoStub->method('hasNewMessages')->willReturn(false);

        $subject = $this->createSubject(
            requestStack: $requestStackStub,
            msgRepo: $msgRepoStub,
        );

        // Act: check post-auth
        $subject->checkPostAuth($this->createStub(User::class));
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
