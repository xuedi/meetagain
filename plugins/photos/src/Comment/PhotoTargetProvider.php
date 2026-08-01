<?php declare(strict_types=1);

namespace Plugin\Photos\Comment;

use App\Activity\ActivityService;
use App\Comment\TargetProviderInterface;
use App\Entity\Comment;
use App\Entity\User;
use Override;
use Plugin\Photos\Activity\Messages\CommentedOnPhoto;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class PhotoTargetProvider implements TargetProviderInterface
{
    public function __construct(
        private PhotoService $photoService,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator,
        private ActivityService $activityService,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return PhotoService::ITEM_TYPE;
    }

    #[Override]
    public function getReturnUrl(int $targetId): ?string
    {
        if ($this->photoService->get($targetId) === null) {
            return null;
        }

        return $this->urlGenerator->generate('app_plugin_photos_photo_show', ['id' => $targetId]);
    }

    #[Override]
    public function canComment(int $targetId): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_USER') && $this->photoService->get($targetId) !== null;
    }

    #[Override]
    public function onCommentCreated(Comment $comment): void
    {
        $user = $comment->getUser();
        if (!$user instanceof User) {
            return;
        }

        $photo = $this->photoService->get((int) $comment->getTargetId());

        $this->activityService->log(CommentedOnPhoto::TYPE, $user, [
            'photo_id' => $comment->getTargetId(),
            'photo_title' => $photo?->getAnyTranslatedTitle() ?? '',
        ]);
    }
}
