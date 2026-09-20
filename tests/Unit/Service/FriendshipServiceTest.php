<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Activity\Messages\FollowedUser;
use App\Activity\Messages\UnFollowedUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Member\BlockingService;
use App\Service\Member\FriendshipService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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
        // Arrange
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
        $userRepoMock->expects($this->once())->method('findOneBy')->with(['id' => $userId])->willReturn($targetUser);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($currentUserMock);
        $emMock->expects($this->once())->method('flush');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($locale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('generate')->with($returnRoute, ['_locale' => $locale, 'id' => $userId])->willReturn($generatedRoute);

        $blockingServiceStub = $this->createStub(BlockingService::class);
        $blockingServiceStub->method('isBlocked')->willReturn(false);

        $activityServiceStub = $this->createStub(ActivityService::class);

        $subject = new FriendshipService(
            repo: $userRepoMock,
            blockingService: $blockingServiceStub,
            em: $emMock,
            router: $routerMock,
            security: $securityStub,
            requestStack: $requestStackStub,
            activityService: $activityServiceStub,
        );

        // Act
        $response = $subject->toggleFollow($userId, $returnRoute);

        // Assert
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowRemovesUserWhenAlreadyFollowing(): void
    {
        // Arrange
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
        $userRepoMock->expects($this->once())->method('findOneBy')->with(['id' => $userId])->willReturn($targetUser);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($currentUserMock);
        $emMock->expects($this->once())->method('flush');

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getLocale')->willReturn($locale);

        $requestStackStub = $this->createStub(RequestStack::class);
        $requestStackStub->method('getCurrentRequest')->willReturn($requestStub);

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('generate')->with($returnRoute, ['_locale' => $locale, 'id' => $userId])->willReturn($generatedRoute);

        $blockingServiceStub = $this->createStub(BlockingService::class);
        $blockingServiceStub->method('isBlocked')->willReturn(false);

        $activityServiceStub = $this->createStub(ActivityService::class);

        $subject = new FriendshipService(
            repo: $userRepoMock,
            blockingService: $blockingServiceStub,
            em: $emMock,
            router: $routerMock,
            security: $securityStub,
            requestStack: $requestStackStub,
            activityService: $activityServiceStub,
        );

        // Act
        $response = $subject->toggleFollow($userId, $returnRoute);

        // Assert
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($generatedRoute, $response->getTargetUrl());
    }

    public function testToggleFollowThrowsExceptionWhenNotAuthenticated(): void
    {
        // Arrange
        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn(null);

        $subject = new FriendshipService(
            repo: $this->createStub(UserRepository::class),
            blockingService: $this->createStub(BlockingService::class),
            em: $this->createStub(EntityManagerInterface::class),
            router: $this->createStub(RouterInterface::class),
            security: $securityStub,
            requestStack: $this->createStub(RequestStack::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Assert
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Should never happen, see: config/packages/security.yaml');

        // Act
        $subject->toggleFollow(42, 'app_profile_view');
    }

    public function testToggleFollowThrowsExceptionWhenUserIsNotUserEntity(): void
    {
        // Arrange
        $nonUserMock = $this->createStub(UserInterface::class);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('getUser')->willReturn($nonUserMock);

        $subject = new FriendshipService(
            repo: $this->createStub(UserRepository::class),
            blockingService: $this->createStub(BlockingService::class),
            em: $this->createStub(EntityManagerInterface::class),
            router: $this->createStub(RouterInterface::class),
            security: $securityStub,
            requestStack: $this->createStub(RequestStack::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Assert
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Should never happen, see: config/packages/security.yaml');

        // Act
        $subject->toggleFollow(42, 'app_profile_view');
    }

    public function testFollowAddsTheTargetAndLogsTheActivityOnce(): void
    {
        // Arrange
        $actor = $this->user(1);
        $target = $this->user(2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->once())->method('log')->with(FollowedUser::TYPE, $actor, ['user_id' => 2]);

        $subject = $this->subject($em, $this->unblocked(), $activityService);

        // Act
        $subject->follow($actor, $target);

        // Assert
        self::assertTrue($actor->getFollowing()->contains($target));
    }

    public function testFollowingTwiceWritesAndLogsOnlyOnce(): void
    {
        // Arrange
        $actor = $this->user(1);
        $target = $this->user(2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->once())->method('log');

        $subject = $this->subject($em, $this->unblocked(), $activityService);

        // Act
        $subject->follow($actor, $target);
        $subject->follow($actor, $target);

        // Assert
        self::assertCount(1, $actor->getFollowing());
    }

    public function testUnfollowRemovesTheTargetAndIsIdempotent(): void
    {
        // Arrange
        $actor = $this->user(1);
        $target = $this->user(2);
        $actor->addFollowing($target);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->once())->method('log')->with(UnFollowedUser::TYPE, $actor, ['user_id' => 2]);

        $subject = $this->subject($em, $this->unblocked(), $activityService);

        // Act
        $subject->unfollow($actor, $target);
        $subject->unfollow($actor, $target);

        // Assert
        self::assertCount(0, $actor->getFollowing());
    }

    public function testFollowAcrossABlockIsRefused(): void
    {
        // Arrange
        $blockingService = $this->createStub(BlockingService::class);
        $blockingService->method('isBlocked')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->never())->method('log');

        $subject = $this->subject($em, $blockingService, $activityService);

        // Act & Assert
        $this->expectException(DomainException::class);
        $subject->follow($this->user(1), $this->user(2));
    }

    public function testFollowingYourselfIsRefused(): void
    {
        // Arrange
        $actor = $this->user(7);
        $subject = $this->subject($this->createStub(EntityManagerInterface::class), $this->unblocked(), $this->createStub(ActivityService::class));

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $subject->follow($actor, $actor);
    }

    private function subject(EntityManagerInterface $em, BlockingService $blockingService, ActivityService $activityService): FriendshipService
    {
        return new FriendshipService(
            repo: $this->createStub(UserRepository::class),
            blockingService: $blockingService,
            em: $em,
            router: $this->createStub(RouterInterface::class),
            security: $this->createStub(Security::class),
            requestStack: $this->createStub(RequestStack::class),
            activityService: $activityService,
        );
    }

    private function unblocked(): BlockingService
    {
        $blockingService = $this->createStub(BlockingService::class);
        $blockingService->method('isBlocked')->willReturn(false);

        return $blockingService;
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
