<?php declare(strict_types=1);

namespace App\Moderation;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Makes one kind of thing reportable by members. Reports are keyed by getTypeKey() plus the subject id.
 */
#[AutoconfigureTag]
interface SubjectProviderInterface
{
    public function getTypeKey(): string;

    /** Null when the subject does not exist or this member may not report it. */
    public function describe(int $id, User $reporter): ?SubjectSnapshot;

    /** Where an admin sees the subject in context, or null when there is no such page. */
    public function getAdminPath(int $id): ?string;

    /** Translation key of the admin removal action, or null when this kind offers none. */
    public function getRemoveLabelKey(): ?string;

    /** Must tolerate a subject that no longer exists. */
    public function remove(int $id): void;
}
