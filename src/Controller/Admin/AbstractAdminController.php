<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

abstract class AbstractAdminController extends SymfonyAbstractController implements AdminNavigationInterface
{
    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                'Should never happen, see: config/packages/security.yaml',
            );
        }

        return $user;
    }
}
