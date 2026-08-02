<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Activity\ActivityService;
use App\Activity\Messages\StartedTopic;
use App\Comment\CommentService;
use App\Entity\Comment;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class TopicService
{
    public const string TYPE = 'topic';

    /**
     * @param iterable<TopicScopeFilterInterface> $scopeFilters
     * @param iterable<EntityActionInterface> $entityActionHandlers
     */
    public function __construct(
        private TopicRepository $topics,
        private CommentRepository $comments,
        private CommentService $commentService,
        private EntityManagerInterface $em,
        private ContentSanitizer $sanitizer,
        private ActivityService $activityService,
        private ScopeIntersection $scopeIntersection,
        #[AutowireIterator(TopicScopeFilterInterface::class)]
        private iterable $scopeFilters,
        #[AutowireIterator(EntityActionInterface::class)]
        private iterable $entityActionHandlers = [],
    ) {}

    public function get(int $id): ?Topic
    {
        $topic = $this->topics->find($id);
        if ($topic === null) {
            return null;
        }

        $allowedIds = $this->resolveTopicIds();

        return $allowedIds === null || in_array($id, $allowedIds, true) ? $topic : null;
    }

    /**
     * @return list<Topic>
     */
    public function getVisible(): array
    {
        return $this->topics->findAllInScope($this->resolveTopicIds());
    }

    /**
     * @return array<int, list<Topic>> visible children keyed by parent id, roots under key 0
     */
    public function getChildrenByParent(): array
    {
        $byParent = [];
        foreach ($this->getVisible() as $topic) {
            $byParent[(int) $topic->getParent()?->getId()][] = $topic;
        }

        return $byParent;
    }

    /**
     * @return list<array{comment: Comment, topic: Topic, topicPath: list<Topic>}>
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $comments = $this->comments->findRecentForTargetType(self::TYPE, $limit, $this->resolveTopicIds());
        if ($comments === []) {
            return [];
        }

        $topics = [];
        foreach ($this->topics->findBy(['id' => array_map(static fn(Comment $c): ?int => $c->getTargetId(), $comments)]) as $topic) {
            $topics[(int) $topic->getId()] = $topic;
        }

        $rows = [];
        foreach ($comments as $comment) {
            $topic = $topics[$comment->getTargetId()] ?? null;
            if ($topic instanceof Topic) {
                $rows[] = ['comment' => $comment, 'topic' => $topic, 'topicPath' => $this->ancestors($topic)];
            }
        }

        return $rows;
    }

    public function countVisible(): int
    {
        return $this->topics->countInScope($this->resolveTopicIds());
    }

    /**
     * @return array<int, int> comment count keyed by topic id
     */
    public function getCommentCounts(): array
    {
        return $this->comments->countPerTargetForType(self::TYPE);
    }

    public function create(User $author, string $title, ?Topic $parent = null): Topic
    {
        $clean = $this->cleanTitle($title);

        if ($parent !== null && $this->depthOf($parent) >= Topic::MAX_DEPTH) {
            throw new InvalidTopicException(InvalidTopicException::REASON_TOO_DEEP);
        }

        $topic = new Topic();
        $topic->setTitle($clean);
        $topic->setAuthor($author);
        $topic->setParent($parent);

        $this->em->persist($topic);
        $this->em->flush();

        $this->dispatch(EntityAction::CreateTopic, (int) $topic->getId());
        $this->activityService->log(StartedTopic::TYPE, $author, ['topic_id' => (int) $topic->getId(), 'topic_title' => $clean]);

        return $topic;
    }

    public function rename(Topic $topic, User $actor, string $title): void
    {
        if (!$this->canRename($topic, $actor)) {
            throw new AccessDeniedException('Cannot rename topic.');
        }

        $topic->setTitle($this->cleanTitle($title));
        $this->em->flush();
    }

    public function delete(Topic $topic, User $actor): void
    {
        if (!$this->canDelete($topic, $actor)) {
            throw new AccessDeniedException('Cannot delete topic.');
        }

        $ids = [(int) $topic->getId(), ...$this->topics->descendantIds($topic)];

        $this->em->remove($topic);
        $this->em->flush();

        foreach ($ids as $id) {
            $this->commentService->deleteAllFor(self::TYPE, $id);
            $this->dispatch(EntityAction::DeleteTopic, $id);
        }
    }

    public function canRename(Topic $topic, User $actor): bool
    {
        return $this->isAdmin($actor) || $topic->getAuthor()?->getId() === $actor->getId();
    }

    public function canDelete(Topic $topic, User $actor): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        if ($topic->getAuthor()?->getId() !== $actor->getId()) {
            return false;
        }

        $hasChildren = $this->topics->countChildren($topic) > 0;
        $hasComments = $this->comments->countForTarget(self::TYPE, (int) $topic->getId()) > 0;

        return !$hasChildren && !$hasComments;
    }

    public function depthOf(Topic $topic): int
    {
        return count($this->ancestors($topic));
    }

    /**
     * @return list<Topic> the chain from the root down to and including the given topic
     */
    public function ancestors(Topic $topic): array
    {
        $chain = [$topic];
        $node = $topic;

        while (($node = $node->getParent()) !== null) {
            array_unshift($chain, $node);
        }

        return $chain;
    }

    /**
     * @return array<int>|null
     */
    public function resolveTopicIds(): ?array
    {
        $lists = [];
        foreach ($this->scopeFilters as $filter) {
            $lists[] = $filter->getTopicIdFilter();
        }

        return $this->scopeIntersection->of($lists);
    }

    private function cleanTitle(string $title): string
    {
        $clean = $this->sanitizer->toPlainText($title);

        if ($clean === '') {
            throw new InvalidTopicException(InvalidTopicException::REASON_EMPTY_TITLE);
        }
        if (mb_strlen($clean) > Topic::MAX_TITLE_LENGTH) {
            throw new InvalidTopicException(InvalidTopicException::REASON_TITLE_TOO_LONG);
        }

        return $clean;
    }

    private function isAdmin(User $actor): bool
    {
        return in_array(UserRole::ROLE_ADMIN, $actor->getRoles(), true);
    }

    private function dispatch(EntityAction $action, int $entityId): void
    {
        foreach ($this->entityActionHandlers as $handler) {
            $handler->onEntityAction($action, $entityId);
        }
    }
}
