<?php declare(strict_types=1);

namespace Tests\Unit\Comment;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnTopic;
use App\Comment\TopicTargetProvider;
use App\Entity\Comment;
use App\Entity\Topic;
use App\Entity\User;
use App\Service\TownHall\AccessService;
use App\Service\TownHall\TopicService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TopicTargetProviderTest extends TestCase
{
    public function testTheTypeKeyIsTheOneTopicsAreStoredUnder(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: null);

        // Act + Assert
        self::assertSame(TopicService::TYPE, $provider->getTypeKey());
    }

    public function testReturnUrlPointsAtTheForumTopicPage(): void
    {
        // Arrange
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_townhall_forum_topic', ['topicId' => 7])
            ->willReturn('/en/townhall/forum/7');
        $provider = $this->makeProvider(topic: new Topic(), urlGenerator: $urlGenerator);

        // Act + Assert
        self::assertSame('/en/townhall/forum/7', $provider->getReturnUrl(7));
    }

    public function testReturnUrlIsNullForATopicOutsideTheScope(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: null);

        // Act + Assert
        self::assertNull($provider->getReturnUrl(7));
    }

    public function testCanCommentRequiresTownHallAccess(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: new Topic(), canAccess: false);

        // Act + Assert
        self::assertFalse($provider->canComment(7));
    }

    public function testCanCommentRequiresATopicInsideTheScope(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: null, canAccess: true);

        // Act + Assert
        self::assertFalse($provider->canComment(7));
    }

    public function testCanCommentRefusesAGuest(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: new Topic(), canAccess: true, user: null);

        // Act + Assert
        self::assertFalse($provider->canComment(7));
    }

    public function testAMemberMayCommentOnAVisibleTopic(): void
    {
        // Arrange
        $provider = $this->makeProvider(topic: new Topic(), canAccess: true);

        // Act + Assert
        self::assertTrue($provider->canComment(7));
    }

    public function testCreationIsLoggedWithTheTopicTitle(): void
    {
        // Arrange
        $user = new User();
        $activityService = $this->createMock(ActivityService::class);
        $activityService
            ->expects(self::once())
            ->method('log')
            ->with(CommentedOnTopic::TYPE, $user, ['topic_id' => 7, 'topic_title' => 'Language']);
        $topic = new Topic()->setTitle('Language');
        $provider = $this->makeProvider(topic: $topic, activityService: $activityService);

        $comment = new Comment();
        $comment->setTargetType(TopicService::TYPE);
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
        $provider = $this->makeProvider(topic: new Topic(), activityService: $activityService);

        // Act
        $provider->onCommentCreated(new Comment());
    }

    private function makeProvider(
        ?Topic $topic,
        bool $canAccess = true,
        ?User $user = new User(),
        ?UrlGeneratorInterface $urlGenerator = null,
        ?ActivityService $activityService = null,
    ): TopicTargetProvider {
        $topicService = $this->createStub(TopicService::class);
        $topicService->method('get')->willReturn($topic);

        $accessService = $this->createStub(AccessService::class);
        $accessService->method('canAccess')->willReturn($canAccess);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new TopicTargetProvider(
            $topicService,
            $accessService,
            $security,
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            $activityService ?? $this->createStub(ActivityService::class),
        );
    }
}
