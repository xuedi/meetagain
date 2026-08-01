<?php declare(strict_types=1);

namespace Tests\Unit\Comment;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnEvent;
use App\Comment\EventTargetProvider;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EventTargetProviderTest extends TestCase
{
    public function testReturnUrlPointsAtTheEventDetailPage(): void
    {
        // Arrange
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_event_details', ['id' => 7])
            ->willReturn('/en/event/7');
        $provider = $this->makeProvider(event: new Event(), urlGenerator: $urlGenerator);

        // Act + Assert
        self::assertSame('/en/event/7', $provider->getReturnUrl(7));
    }

    public function testReturnUrlIsNullForAMissingEvent(): void
    {
        // Arrange
        $provider = $this->makeProvider(event: null);

        // Act + Assert
        self::assertNull($provider->getReturnUrl(7));
    }

    public function testCanCommentDelegatesToThePermissionAttribute(): void
    {
        // Arrange
        $event = new Event();
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->with(PermissionAttribute::EVENT_COMMENT_CREATE, $event)
            ->willReturn(true);
        $provider = $this->makeProvider(event: $event, authorizationChecker: $authorizationChecker);

        // Act + Assert
        self::assertTrue($provider->canComment(7));
    }

    public function testCanCommentIsFalseForAMissingEvent(): void
    {
        // Arrange
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');
        $provider = $this->makeProvider(event: null, authorizationChecker: $authorizationChecker);

        // Act + Assert
        self::assertFalse($provider->canComment(7));
    }

    public function testCreationIsLoggedAsActivity(): void
    {
        // Arrange
        $user = new User();
        $activityService = $this->createMock(ActivityService::class);
        $activityService
            ->expects(self::once())
            ->method('log')
            ->with(CommentedOnEvent::TYPE, $user, ['event_id' => 7]);
        $provider = $this->makeProvider(event: new Event(), activityService: $activityService);

        $comment = new Comment();
        $comment->setTargetType(EventTargetProvider::TYPE);
        $comment->setTargetId(7);
        $comment->setUser($user);

        // Act
        $provider->onCommentCreated($comment);
    }

    public function testAuthorlessCommentIsNotLogged(): void
    {
        // Arrange
        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects(self::never())->method('log');
        $provider = $this->makeProvider(event: new Event(), activityService: $activityService);

        // Act
        $provider->onCommentCreated(new Comment());
    }

    private function makeProvider(
        ?Event $event,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?ActivityService $activityService = null,
    ): EventTargetProvider {
        $events = $this->createStub(EventRepository::class);
        $events->method('find')->willReturn($event);

        return new EventTargetProvider(
            $events,
            $authorizationChecker ?? $this->createStub(AuthorizationCheckerInterface::class),
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            $activityService ?? $this->createStub(ActivityService::class),
        );
    }
}
