<?php declare(strict_types=1);

namespace Tests\Unit\Service\TownHall;

use App\Activity\ActivityService;
use App\Activity\Messages\StartedTopic;
use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Entity\Topic;
use App\Entity\User;
use App\EntityActionInterface;
use App\Enum\EntityAction;
use App\Enum\UserRole;
use App\Filter\TownHall\ScopeIntersection;
use App\Filter\TownHall\TopicScopeFilterInterface;
use App\Repository\CommentRepository;
use App\Repository\TopicRepository;
use App\Service\Security\ContentSanitizer;
use App\Service\TownHall\InvalidTopicException;
use App\Service\TownHall\TopicService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Unit\Stubs\UserStub;

class TopicServiceTest extends TestCase
{
    public function testCreateStripsMarkupPersistsAndAnnouncesTheTopic(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $author = $this->makeUser(1);
        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects(self::once())->method('log')->with(StartedTopic::TYPE, $author, ['topic_id' => 0, 'topic_title' => 'Language']);
        $dispatched = [];

        $service = $this->makeService(em: $em, activityService: $activityService, entityActionHandlers: [$this->makeRecordingHandler($dispatched)]);

        // Act
        $topic = $service->create($author, '  <b>Language</b>  ');

        // Assert
        self::assertSame('Language', $topic->getTitle());
        self::assertSame($author, $topic->getAuthor());
        self::assertNull($topic->getParent());
        self::assertSame(['create_topic:0'], $dispatched);
    }

    public static function rejectedTitleProvider(): iterable
    {
        yield 'blank input' => ['   ', InvalidTopicException::REASON_EMPTY_TITLE];
        yield 'markup with no text' => ['<script>alert(1)</script>', InvalidTopicException::REASON_EMPTY_TITLE];
        yield 'over the length cap' => [str_repeat('x', Topic::MAX_TITLE_LENGTH + 1), InvalidTopicException::REASON_TITLE_TOO_LONG];
    }

    #[DataProvider('rejectedTitleProvider')]
    public function testCreateRejectsAnInvalidTitle(string $title, string $expectedReason): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService(em: $em);

        // Act + Assert
        try {
            $service->create($this->makeUser(1), $title);
            self::fail('Expected InvalidTopicException.');
        } catch (InvalidTopicException $exception) {
            self::assertSame($expectedReason, $exception->reason);
        }
    }

    public function testCreateAcceptsAChildOfTheSecondLevel(): void
    {
        // Arrange
        $service = $this->makeService();
        $parent = $this->makeTopic(2, $this->makeTopic(1));

        // Act
        $topic = $service->create($this->makeUser(1), 'learn materials', $parent);

        // Assert
        self::assertSame($parent, $topic->getParent());
    }

    public function testCreateRefusesToNestBelowTheDepthCap(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService(em: $em);
        $deepest = $this->makeTopic(3, $this->makeTopic(2, $this->makeTopic(1)));

        // Act + Assert
        try {
            $service->create($this->makeUser(1), 'too deep', $deepest);
            self::fail('Expected InvalidTopicException.');
        } catch (InvalidTopicException $exception) {
            self::assertSame(InvalidTopicException::REASON_TOO_DEEP, $exception->reason);
        }
    }

    public static function depthProvider(): iterable
    {
        yield 'a root counts as the first level' => [1, 1];
        yield 'a child counts as the second level' => [2, 2];
        yield 'a grandchild counts as the third level' => [3, 3];
    }

    #[DataProvider('depthProvider')]
    public function testDepthOf(int $generations, int $expected): void
    {
        // Arrange
        $service = $this->makeService();
        $topic = null;
        for ($level = 1; $level <= $generations; $level++) {
            $topic = $this->makeTopic($level, $topic);
        }

        // Act + Assert
        self::assertSame($expected, $service->depthOf($topic));
    }

    public static function deletePermissionProvider(): iterable
    {
        yield 'author of an empty topic may delete' => [1, UserRole::User, 0, 0, true];
        yield 'author may not delete a topic with children' => [1, UserRole::User, 1, 0, false];
        yield 'author may not delete a topic with comments' => [1, UserRole::User, 0, 3, false];
        yield 'a stranger may not delete' => [2, UserRole::User, 0, 0, false];
        yield 'an admin may delete a populated topic' => [2, UserRole::Admin, 2, 5, true];
    }

    #[DataProvider('deletePermissionProvider')]
    public function testCanDelete(int $actorId, UserRole $role, int $children, int $comments, bool $expected): void
    {
        // Arrange
        $topics = $this->createStub(TopicRepository::class);
        $topics->method('countChildren')->willReturn($children);
        $commentRepo = $this->createStub(CommentRepository::class);
        $commentRepo->method('countForTarget')->willReturn($comments);
        $service = $this->makeService(topics: $topics, comments: $commentRepo);

        $topic = $this->makeTopic(7);
        $topic->setAuthor($this->makeUser(1));
        $actor = $this->makeUser($actorId)->setRole($role);

        // Act + Assert
        self::assertSame($expected, $service->canDelete($topic, $actor));
    }

    public function testDeleteCleansUpCommentsAndAnnouncesEveryNodeOfTheSubtree(): void
    {
        // Arrange
        $topics = $this->createStub(TopicRepository::class);
        $topics->method('descendantIds')->willReturn([8, 9]);

        $cleaned = [];
        $commentRepo = $this->createStub(CommentRepository::class);
        $commentRepo
            ->method('deleteForTarget')
            ->willReturnCallback(static function (string $type, int $id) use (&$cleaned): int {
                $cleaned[] = $type . ':' . $id;

                return 0;
            });

        $dispatched = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove');
        $service = $this->makeService(topics: $topics, comments: $commentRepo, em: $em, entityActionHandlers: [$this->makeRecordingHandler($dispatched)]);

        $topic = $this->makeTopic(7);
        $admin = $this->makeUser(2)->setRole(UserRole::Admin);

        // Act
        $service->delete($topic, $admin);

        // Assert
        self::assertSame(['topic:7', 'topic:8', 'topic:9'], $cleaned);
        self::assertSame(['delete_topic:7', 'delete_topic:8', 'delete_topic:9'], $dispatched);
    }

    public function testDeleteRefusesAStranger(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $service = $this->makeService(em: $em);
        $topic = $this->makeTopic(7)->setAuthor($this->makeUser(1));

        // Act + Assert
        $this->expectException(AccessDeniedException::class);
        $service->delete($topic, $this->makeUser(2));
    }

    public static function renamePermissionProvider(): iterable
    {
        yield 'the author may rename' => [1, UserRole::User, true];
        yield 'a stranger may not rename' => [2, UserRole::User, false];
        yield 'an admin may rename anything' => [2, UserRole::Admin, true];
    }

    #[DataProvider('renamePermissionProvider')]
    public function testCanRename(int $actorId, UserRole $role, bool $expected): void
    {
        // Arrange
        $service = $this->makeService();
        $topic = $this->makeTopic(7)->setAuthor($this->makeUser(1));
        $actor = $this->makeUser($actorId)->setRole($role);

        // Act + Assert
        self::assertSame($expected, $service->canRename($topic, $actor));
    }

    public function testRenameSanitizesTheNewTitle(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService(em: $em);
        $author = $this->makeUser(1);
        $topic = $this->makeTopic(7)->setAuthor($author)->setTitle('old');

        // Act
        $service->rename($topic, $author, ' <i>new</i> ');

        // Assert
        self::assertSame('new', $topic->getTitle());
    }

    public function testRenameRefusesAStranger(): void
    {
        // Arrange
        $service = $this->makeService();
        $topic = $this->makeTopic(7)->setAuthor($this->makeUser(1))->setTitle('old');

        // Act + Assert
        $this->expectException(AccessDeniedException::class);
        $service->rename($topic, $this->makeUser(2), 'new');
    }

    public function testGetReturnsTheTopicWhenNoFilterHasAnOpinion(): void
    {
        // Arrange
        $topic = $this->makeTopic(7);
        $topics = $this->createStub(TopicRepository::class);
        $topics->method('find')->willReturn($topic);
        $service = $this->makeService(topics: $topics, scopeFilters: [$this->makeScopeFilter(null)]);

        // Act + Assert
        self::assertSame($topic, $service->get(7));
    }

    public function testGetHidesATopicOutsideTheResolvedScope(): void
    {
        // Arrange
        $topics = $this->createStub(TopicRepository::class);
        $topics->method('find')->willReturn($this->makeTopic(7));
        $service = $this->makeService(topics: $topics, scopeFilters: [$this->makeScopeFilter([1, 2])]);

        // Act + Assert
        self::assertNull($service->get(7));
    }

    /**
     * @param array<TopicScopeFilterInterface> $scopeFilters
     * @param array<EntityActionInterface> $entityActionHandlers
     */
    private function makeService(
        ?TopicRepository $topics = null,
        ?CommentRepository $comments = null,
        ?EntityManagerInterface $em = null,
        ?ActivityService $activityService = null,
        array $scopeFilters = [],
        array $entityActionHandlers = [],
    ): TopicService {
        $config = new HtmlSanitizerConfig()->allowSafeElements();
        $sanitizer = new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config));
        $commentRepo = $comments ?? $this->createStub(CommentRepository::class);
        $entityManager = $em ?? $this->createStub(EntityManagerInterface::class);

        return new TopicService(
            $topics ?? $this->createStub(TopicRepository::class),
            $commentRepo,
            new CommentService($commentRepo, $entityManager, $sanitizer, new TargetRegistry([])),
            $entityManager,
            $sanitizer,
            $activityService ?? $this->createStub(ActivityService::class),
            new ScopeIntersection(),
            $scopeFilters,
            $entityActionHandlers,
        );
    }

    /**
     * @param array<string> $dispatched
     */
    private function makeRecordingHandler(array &$dispatched): EntityActionInterface
    {
        $handler = $this->createStub(EntityActionInterface::class);
        $handler
            ->method('onEntityAction')
            ->willReturnCallback(static function (EntityAction $action, int $entityId) use (&$dispatched): void {
                $dispatched[] = $action->value . ':' . $entityId;
            });

        return $handler;
    }

    /**
     * @param array<int>|null $ids
     */
    private function makeScopeFilter(?array $ids): TopicScopeFilterInterface
    {
        $filter = $this->createStub(TopicScopeFilterInterface::class);
        $filter->method('getTopicIdFilter')->willReturn($ids);

        return $filter;
    }

    private function makeTopic(int $id, ?Topic $parent = null): Topic
    {
        $topic = new Topic();
        new ReflectionProperty(Topic::class, 'id')->setValue($topic, $id);
        $topic->setParent($parent);

        return $topic;
    }

    private function makeUser(int $id): User
    {
        return new UserStub()->setId($id);
    }
}
