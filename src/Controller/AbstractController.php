<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

abstract class AbstractController extends AbstractSymfonyController
{
    protected function getAuthedUser(): User
    {
 // just to avoid phpstorms null pointer warning
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                "Should never happen, see: config/packages/security.yaml"
            );
        }

        return $user;
    }
}
