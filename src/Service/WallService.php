<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\WallPost;
use App\Entity\WallReply;
use App\EntityActionInterface;
use App\Enum\EntityAction;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class WallService
{
    /**
     * @param iterable<EntityActionInterface> $entityActionHandlers
     */
    public function __construct(
        private EntityManagerInterface $em,
        #[AutowireIterator(EntityActionInterface::class)]
        private iterable $entityActionHandlers = [],
    ) {}

    public function createPost(User $author, string $content): WallPost
    {
        $post = new WallPost();
        $post->setAuthor($author);
        $post->setContent(trim($content));

        $this->em->persist($post);
        $this->em->flush();

        $this->dispatch(EntityAction::CreateWallPost, (int) $post->getId());

        return $post;
    }

    public function createReply(WallPost $post, User $author, string $content): WallReply
    {
        $reply = new WallReply();
        $reply->setPost($post);
        $reply->setAuthor($author);
        $reply->setContent(trim($content));

        $this->em->persist($reply);
        $this->em->flush();

        return $reply;
    }

    public function deletePost(WallPost $post, User $actor): void
    {
        if (!$this->canDeletePost($post, $actor)) {
            throw new AccessDeniedException('Cannot delete wall post.');
        }

        $postId = (int) $post->getId();
        $this->em->remove($post);
        $this->em->flush();

        $this->dispatch(EntityAction::DeleteWallPost, $postId);
    }

    public function deleteReply(WallReply $reply, User $actor): void
    {
        if (!$this->canDeleteReply($reply, $actor)) {
            throw new AccessDeniedException('Cannot delete wall reply.');
        }

        $this->em->remove($reply);
        $this->em->flush();
    }

    public function editPost(WallPost $post, User $actor, string $newContent): void
    {
        if ($post->getAuthor()?->getId() !== $actor->getId()) {
            throw new AccessDeniedException('Cannot edit wall post.');
        }

        $post->setContent(trim($newContent));
        $post->setEditedAt(new DateTimeImmutable());

        $this->em->flush();
    }

    public function canDeletePost(WallPost $post, User $actor): bool
    {
        if (in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            return true;
        }

        return $post->getAuthor()?->getId() === $actor->getId();
    }

    public function canDeleteReply(WallReply $reply, User $actor): bool
    {
        if (in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            return true;
        }

        return $reply->getAuthor()?->getId() === $actor->getId();
    }

    private function dispatch(EntityAction $action, int $entityId): void
    {
        foreach ($this->entityActionHandlers as $handler) {
            $handler->onEntityAction($action, $entityId);
        }
    }
}
