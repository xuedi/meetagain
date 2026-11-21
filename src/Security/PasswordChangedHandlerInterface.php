<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Runs after an account's password was reset or changed. Every implementation runs; it may end credentials
 * that outlive the session, such as stored tokens.
 */
#[AutoconfigureTag]
interface PasswordChangedHandlerInterface
{
    public function onPasswordChanged(User $user): void;
}
