<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserBlockRepository;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class FriendshipServiceTest extends TestCase
{
    public function testToggleFollowAddsUserWhenNotFollowing(): void
    {
        // Arrange: set up current user not following target user
        $userId = 42;
        $returnRoute = 'app_profile_view';
        $locale = 'en';
        $generatedRoute = '/en/profile/42';

        $followingCollection = $this->createStub(Collection::class);
        $followingCollection->method('contains')->willReturn(false);

        $currentUserMock = $this->createMock(User::class);
        $currentUserMock->method('getFollowing')->willReturn($followingCollection);
        $currentUserMock->expects($this->once())->method('addFollowing');

        $targetUser = $this->createStub(User::class);
        $targetUser->method('getId')->willReturn($userId);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($currentUserMock);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($targetUser);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($currentUserMock);
        $emMock->expects($this->once())->method('flush');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($locale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock
            ->expects($this->once())
            ->method('generate')
            ->with($returnRoute, ['_locale' => $locale, 'id' => $userId])
            ->willReturn($generatedRoute);

        $blockRepoStub = $this->createStub(UserBlockRepository::class);
        $blockRepoStub->method('isBlockedEitherWay')->willReturn(false);

        $subject = new FriendshipService(
            repo: $userRepoMock,
            blockRepo: $blockRepoStub,
            em: $emMock,
            router: $routerMock,
            security: $securityStub,
            requestStack: $requestStackStub,
        );

        // Act: toggle follow
        $response = $subject->toggleFollow($userId, $returnRoute);

        // Assert: returns redirect to generated route
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowRemovesUserWhenAlreadyFollowing(): void
    {
        // Arrange: set up current user already following target user
        $userId = 42;
        $returnRoute = 'app_profile_view';
        $locale = 'en';
        $generatedRoute = '/en/profile/42';

        $followingCollection = $this->createStub(Collection::class);
        $followingCollection->method('contains')->willReturn(true);

        $currentUserMock = $this->createMock(User::class);
        $currentUserMock->method('getFollowing')->willReturn($followingCollection);
        $currentUserMock->expects($this->once())->method('removeFollowing');

        $targetUser = $this->createStub(User::class);
        $targetUser->method('getId')->willReturn($userId);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($currentUserMock);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($targetUser);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($currentUserMock);
        $emMock->expects($this->once())->method('flush');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($locale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock
            ->expects($this->once())
            ->method('generate')
            ->with($returnRoute, ['_locale' => $locale, 'id' => $userId])
            ->willReturn($generatedRoute);

        $blockRepoStub = $this->createStub(UserBlockRepository::class);
        $blockRepoStub->method('isBlockedEitherWay')->willReturn(false);

        $subject = new FriendshipService(
            repo: $userRepoMock,
            blockRepo: $blockRepoStub,
            em: $emMock,
            router: $routerMock,
            security: $securityStub,
            requestStack: $requestStackStub,
        );

        // Act: toggle follow
        $response = $subject->toggleFollow($userId, $returnRoute);

        // Assert: returns redirect to generated route
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowThrowsExceptionWhenNotAuthenticated(): void
    {
        // Arrange: security returns null (unauthenticated)
        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn(null);

        $subject = new FriendshipService(
            repo: $this->createStub(UserRepository::class),
            blockRepo: $this->createStub(UserBlockRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            router: $this->createStub(RouterInterface::class),
            security: $securityStub,
            requestStack: $this->createStub(RequestStack::class),
        );

        // Assert: expect authentication exception
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage('Should never happen, see: config/packages/security.yaml');

        // Act: toggle follow without authentication
        $subject->toggleFollow(42, 'app_profile_view');
    }

    public function testToggleFollowThrowsExceptionWhenUserIsNotUserEntity(): void
    {
        // Arrange: security returns non-User object implementing UserInterface
        $nonUserMock = $this->createStub(UserInterface::class);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($nonUserMock);

        $subject = new FriendshipService(
            repo: $this->createStub(UserRepository::class),
            blockRepo: $this->createStub(UserBlockRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            router: $this->createStub(RouterInterface::class),
            security: $securityStub,
            requestStack: $this->createStub(RequestStack::class),
        );

        // Assert: expect authentication exception
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessage('Should never happen, see: config/packages/security.yaml');

        // Act: toggle follow with non-User object
        $subject->toggleFollow(42, 'app_profile_view');
    }
}
