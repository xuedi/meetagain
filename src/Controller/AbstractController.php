<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\WebLink\Link;

abstract class AbstractController extends AbstractSymfonyController
{
    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!($user instanceof User)) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }

    protected function getResponse(): Response
    {
        $links = [
            (new Link(href: '/stylesheet/bulma.min.css'))->withAttribute('as', 'style'),
            (new Link(href: '/stylesheet/fontawesome.min.css'))->withAttribute('as', 'style'),
            (new Link(href: '/stylesheet/fontawesome-solid.css'))->withAttribute('as', 'style'),
            (new Link(href: '/stylesheet/fonts.css'))->withAttribute('as', 'style'),
            (new Link(href: '/stylesheet/custom.css'))->withAttribute('as', 'style'),
            (new Link(href: '/javascript/custom.js'))->withAttribute('as', 'script'),
            (new Link(href: '/fonts/fa-solid-900.woff2'))->withAttribute('as', 'font'),
        ];

        return $this->sendEarlyHints($links);
    }
}
