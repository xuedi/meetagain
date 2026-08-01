<?php declare(strict_types=1);

namespace App\Comment;

use App\Activity\ActivityService;
use App\Activity\Messages\CommentedOnEvent;
use App\Entity\Comment;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class EventTargetProvider implements TargetProviderInterface
{
    public const string TYPE = 'event';

    public function __construct(
        private EventRepository $events,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator,
        private ActivityService $activityService,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getReturnUrl(int $targetId): ?string
    {
        if ($this->events->find($targetId) === null) {
            return null;
        }

        return $this->urlGenerator->generate('app_event_details', ['id' => $targetId]);
    }

    #[Override]
    public function canComment(int $targetId): bool
    {
        $event = $this->events->find($targetId);

        return $event !== null && $this->authorizationChecker->isGranted(PermissionAttribute::EVENT_COMMENT_CREATE, $event);
    }

    #[Override]
    public function onCommentCreated(Comment $comment): void
    {
        $user = $comment->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activityService->log(CommentedOnEvent::TYPE, $user, ['event_id' => $comment->getTargetId()]);
    }
}
