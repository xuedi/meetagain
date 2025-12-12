<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Repository\MessageRepository;
use App\Security\UserChecker;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

#[AllowMockObjectsWithoutExpectations]
class UserCheckerTest extends TestCase
{
    private MockObject|ActivityService $activityService;
    private MockObject|EntityManagerInterface $em;
    private MockObject|RequestStack $requestStack;
    private MockObject|MessageRepository $msgRepo;
    private UserChecker $subject;

    protected function setUp(): void
    {
        $this->activityService = $this->createMock(ActivityService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->msgRepo = $this->createMock(MessageRepository::class);

        $this->subject = new UserChecker($this->activityService, $this->em, $this->requestStack, $this->msgRepo);
    }

    public function testAbortCheckPreAuthWithNonUserObject(): void
    {
        $nonUserObject = $this->createMock(UserInterface::class);
        $this->subject->checkPreAuth($nonUserObject);

        $this->assertTrue(true);
    }

    public function testCheckPreAuthWithActiveUser(): void
    {
        $activeUser = $this->createMock(User::class);
        $activeUser->method('getStatus')->willReturn(UserStatus::Active);

        $this->subject->checkPreAuth($activeUser);

        $this->assertTrue(true);
    }

    public function testCheckPreAuthWithNonActiveUser(): void
    {
        // Create a mock of User with non-Active status (e.g., Registered)
        $nonActiveUser = $this->createMock(User::class);
        $nonActiveUser->method('getStatus')->willReturn(UserStatus::Registered);

        // This should throw a CustomUserMessageAccountStatusException
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('The user is not anymore or not jet active');

        $this->subject->checkPreAuth($nonActiveUser);
    }

    public function testWillAbortInFailureOfGettingUser(): void
    {
        $nonUserObject = $this->createMock(UserInterface::class);
        $this->subject->checkPostAuth($nonUserObject);

        $this->assertTrue(true);
    }

    public function testWillAbortInFailureOfGettingRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->subject->checkPostAuth($this->createMock(User::class));

        $this->assertTrue(true);
    }

    public function testSuccessfulOnPostAuth(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->exactly(2))->method('set');

        $requestMock = $this->createMock(Request::class);
        $requestMock->method('getSession')->willReturn($sessionMock);

        $this->requestStack->method('getCurrentRequest')->willReturn($requestMock);

        $this->em->expects($this->once())->method('persist');

        $this->em->expects($this->once())->method('flush');

        $this->em->expects($this->once())->method('flush');

        $this->activityService->expects($this->once())->method('log');

        $this->msgRepo
            ->expects($this->once())
            ->method('hasNewMessages')
            ->willReturn(true);

        $this->subject->checkPostAuth($this->createMock(User::class));

        $this->assertTrue(true);
    }
}
