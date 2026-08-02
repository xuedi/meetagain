<?php declare(strict_types=1);

namespace App\Comment;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CommentService
{
    public function __construct(
        private CommentRepository $repository,
        private EntityManagerInterface $em,
        private ContentSanitizer $sanitizer,
        private TargetRegistry $registry,
    ) {}

    /**
     * @return array<Comment>
     */
    public function getFor(string $targetType, int $targetId): array
    {
        return $this->repository->findForTarget($targetType, $targetId);
    }

    public function create(string $targetType, int $targetId, User $user, string $content): Comment
    {
        $clean = $this->sanitizer->toPlainText($content);
        if ($clean === '') {
            throw new InvalidContentException(InvalidContentException::REASON_EMPTY);
        }
        if (mb_strlen($clean) > Comment::MAX_CONTENT_LENGTH) {
            throw new InvalidContentException(InvalidContentException::REASON_TOO_LONG);
        }

        $comment = new Comment();
        $comment->setTargetType($targetType);
        $comment->setTargetId($targetId);
        $comment->setUser($user);
        $comment->setContent($clean);
        $comment->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($comment);
        $this->em->flush();

        $this->registry->providerFor($targetType)?->onCommentCreated($comment);

        return $comment;
    }

    public function canDelete(Comment $comment, User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $comment->getUser()?->getId() === $user->getId();
    }

    public function delete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    public function deleteAllFor(string $targetType, int $targetId): void
    {
        $this->repository->deleteForTarget($targetType, $targetId);
    }
}
