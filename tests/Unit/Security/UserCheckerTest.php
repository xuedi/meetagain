<?php declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\MessageRepository;
use App\Security\UserChecker;
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

    public function testCheckPreAuthThrowsExceptionForNonActiveUsers(): void
    {
        // Arrange
        $nonActiveUser = $this->createStub(User::class);
        $nonActiveUser->method('getStatus')->willReturn(UserStatus::Registered);

        $subject = $this->createSubject();

        // Assert
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('The user is not anymore or not jet active');

        // Act
        $subject->checkPreAuth($nonActiveUser);
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
        $subject->checkPostAuth($this->createStub(User::class));
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
        $subject->checkPostAuth($this->createStub(User::class));
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
