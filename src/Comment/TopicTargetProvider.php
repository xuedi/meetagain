<?php declare(strict_types=1);

namespace App\Comment;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnTopic;
use App\Entity\User;
use App\Service\TownHall\AccessService;
use App\Service\TownHall\TopicService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em,
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
    public function onCommentCreated(int $targetId, int $userId): void
    {
        $topic = $this->topicService->get($targetId);

        $this->activityService->log(CommentedOnTopic::TYPE, $this->em->getReference(User::class, $userId), [
            'topic_id' => $targetId,
            'topic_title' => $topic?->getTitle() ?? '',
        ]);
    }
}
