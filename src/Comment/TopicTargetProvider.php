<?php declare(strict_types=1);

namespace App\Comment;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnTopic;
use App\Entity\Comment;
use App\Entity\User;
use App\Service\TownHall\AccessService;
use App\Service\TownHall\TopicService;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TopicTargetProvider implements TargetProviderInterface
{
    public function __construct(
        private TopicService $topicService,
        private AccessService $accessService,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private ActivityService $activityService,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return TopicService::TYPE;
    }

    #[Override]
    public function getReturnUrl(int $targetId): ?string
    {
        if ($this->topicService->get($targetId) === null) {
            return null;
        }

        return $this->urlGenerator->generate('app_townhall_forum_topic', ['topicId' => $targetId]);
    }

    #[Override]
    public function canComment(int $targetId): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $this->accessService->canAccess($user) && $this->topicService->get($targetId) !== null;
    }

    #[Override]
    public function onCommentCreated(Comment $comment): void
    {
        $user = $comment->getUser();
        if (!$user instanceof User) {
            return;
        }

        $topicId = (int) $comment->getTargetId();
        $topic = $this->topicService->get($topicId);

        $this->activityService->log(CommentedOnTopic::TYPE, $user, [
            'topic_id' => $topicId,
            'topic_title' => $topic?->getTitle() ?? '',
        ]);
    }
}
