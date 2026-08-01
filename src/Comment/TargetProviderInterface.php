<?php declare(strict_types=1);

namespace App\Comment;

use App\Entity\Comment;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one kind of thing as commentable. The registry keys providers by getTypeKey().
 */
#[AutoconfigureTag]
interface TargetProviderInterface
{
    /** Registry key for this target kind; the value stored in Comment::targetType. */
    public function getTypeKey(): string;

    /** Detail-page URL of the target, or null when it does not exist. */
    public function getReturnUrl(int $targetId): ?string;

    /** Whether the current visitor may add a comment to this target. */
    public function canComment(int $targetId): bool;

    /** Side effects the target owns, run after the comment is persisted. */
    public function onCommentCreated(Comment $comment): void;
}
