<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Vetoes the direct edit from the event page for a viewer who may administer the event. Returning false sends that
 * viewer to the suggestion page instead.
 */
#[AutoconfigureTag]
interface DirectEditFilterInterface
{
    public function allowsDirectEdit(Event $event, User $user): bool;
}
