<?php declare(strict_types=1);

namespace App\Moderation\Subject;

use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Entity\User;
use App\Moderation\SubjectProviderInterface;
use App\Moderation\SubjectSnapshot;
use Override;

final readonly class CommentSubject implements SubjectProviderInterface
{
    public const string TYPE = 'comment';
    private const int EXCERPT_LENGTH = 1000;

    public function __construct(
        private CommentService $commentService,
        private TargetRegistry $targetRegistry,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function describe(int $id, User $reporter): ?SubjectSnapshot
    {
        $comment = $this->commentService->findComment($id);
        $author = $comment?->getUser();
        if ($author === null || $author->getId() === $reporter->getId()) {
            return null;
        }

        $targetType = (string) $comment->getTargetType();
        $targetId = (int) $comment->getTargetId();
        if ($this->targetRegistry->providerFor($targetType)?->getReturnUrl($targetId) === null) {
            return null;
        }

        return new SubjectSnapshot(
            label: sprintf('%s #%d', $targetType, $targetId),
            excerpt: mb_substr((string) $comment->getContent(), 0, self::EXCERPT_LENGTH),
            author: $author,
        );
    }

    #[Override]
    public function getAdminPath(int $id): ?string
    {
        $comment = $this->commentService->findComment($id);
        if ($comment === null) {
            return null;
        }

        return $this->targetRegistry->providerFor((string) $comment->getTargetType())?->getReturnUrl((int) $comment->getTargetId());
    }

    #[Override]
    public function getRemoveLabelKey(): ?string
    {
        return 'admin_support_moderation.button_remove_comment';
    }

    #[Override]
    public function remove(int $id): void
    {
        $comment = $this->commentService->findComment($id);
        if ($comment === null) {
            return;
        }

        $this->commentService->delete($comment);
    }
}
