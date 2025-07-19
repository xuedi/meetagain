<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class FriendshipServiceTest extends TestCase
{
    private MockObject|UserRepository $userRepositoryMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|RouterInterface $routerMock;
    private MockObject|Security $securityMock;
    private MockObject|RequestStack $requestStackMock;
    private FriendshipService $subject;

    protected function setUp(): void
    {
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->securityMock = $this->createMock(Security::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);

        $this->subject = new FriendshipService(
            repo: $this->userRepositoryMock,
            em: $this->entityManagerMock,
            router: $this->routerMock,
            security: $this->securityMock,
            requestStack: $this->requestStackMock,
        );
    }

    public function testToggleFollowAddUser(): void
    {
        // Test data
        $userId = 42;
        $returnRoute = 'app_profile_view';
        $locale = 'en';
        $generatedRoute = '/en/profile/42';

        // Mock current user
        $currentUser = $this->createMock(User::class);
        $followingCollection = $this->createMock(Collection::class);
        $followingCollection
            ->method('contains')
            ->willReturn(false);
        $currentUser
            ->method('getFollowing')
            ->willReturn($followingCollection);
        $currentUser
            ->expects($this->once())
            ->method('addFollowing');

        // Mock target user
        $targetUser = $this->createMock(User::class);
        $targetUser
            ->method('getId')
            ->willReturn($userId);

        // Mock security to return current user
        $this->securityMock
            ->method('getUser')
            ->willReturn($currentUser);

        // Mock user repository to return target user
        $this->userRepositoryMock
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($targetUser);

        // Mock entity manager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($currentUser);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        // Mock request
        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->method('getLocale')
            ->willReturn($locale);
        $this->requestStackMock
            ->method('getCurrentRequest')
            ->willReturn($requestMock);

        // Mock router
        $this->routerMock
            ->method('generate')
            ->with(
                $returnRoute,
                [
                    '_locale' => $locale,
                    'id' => $userId
                ]
            )
            ->willReturn($generatedRoute);

        // Call the method
        $response = $this->subject->toggleFollow($userId, $returnRoute);

        // Assert response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowRemoveUser(): void
    {
        // Test data
        $userId = 42;
        $returnRoute = 'app_profile_view';
        $locale = 'en';
        $generatedRoute = '/en/profile/42';

        // Mock current user
        $currentUser = $this->createMock(User::class);
        $followingCollection = $this->createMock(Collection::class);
        $followingCollection
            ->method('contains')
            ->willReturn(true);
        $currentUser
            ->method('getFollowing')
            ->willReturn($followingCollection);
        $currentUser
            ->expects($this->once())
            ->method('removeFollowing');

        // Mock target user
        $targetUser = $this->createMock(User::class);
        $targetUser
            ->method('getId')
            ->willReturn($userId);

        // Mock security to return current user
        $this->securityMock
            ->method('getUser')
            ->willReturn($currentUser);

        // Mock user repository to return target user
        $this->userRepositoryMock
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($targetUser);

        // Mock entity manager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($currentUser);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        // Mock request
        $requestMock = $this->createMock(Request::class);
        $requestMock
            ->method('getLocale')
            ->willReturn($locale);
        $this->requestStackMock
            ->method('getCurrentRequest')
            ->willReturn($requestMock);

        // Mock router
        $this->routerMock
            ->method('generate')
            ->with(
                $returnRoute,
                [
                    '_locale' => $locale,
                    'id' => $userId
                ]
            )
            ->willReturn($generatedRoute);

        // Call the method
        $response = $this->subject->toggleFollow($userId, $returnRoute);

        // Assert response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowUnauthenticatedUser(): void
    {
        // Test data
        $userId = 42;
        $returnRoute = 'app_profile_view';

        // Mock security to return null (unauthenticated)
        $this->securityMock
            ->method('getUser')
            ->willReturn(null);

        // Expect exception
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage('Should never happen, see: config/packages/security.yaml');

        // Call the method
        $this->subject->toggleFollow($userId, $returnRoute);
    }

    public function testToggleFollowNonUserAuthenticated(): void
    {
        // Test data
        $userId = 42;
        $returnRoute = 'app_profile_view';

        // Create a mock that implements UserInterface but is not a User
        $nonUserMock = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // Mock security to return a non-User object
        $this->securityMock
            ->method('getUser')
            ->willReturn($nonUserMock);

        // Expect exception
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage('Should never happen, see: config/packages/security.yaml');

        // Call the method
        $this->subject->toggleFollow($userId, $returnRoute);
    }
}