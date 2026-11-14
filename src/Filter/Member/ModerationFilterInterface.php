<?php declare(strict_types=1);

namespace App\Filter\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Refuses a moderation action of one member on another. Returning false refuses it; null means no opinion.
 */
#[AutoconfigureTag]
interface ModerationFilterInterface
{
    public function mayModerate(User $actor, User $target): ?bool;
}
