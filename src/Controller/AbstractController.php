<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\WebLink\Link;

abstract class AbstractController extends AbstractSymfonyController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            AssetMapperInterface::class,
        ]);
    }

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

    protected function getResponse(): Response
    {
        /** @var AssetMapperInterface $assetMapper */
        $assetMapper = $this->container->get(AssetMapperInterface::class);

        $preloads = [
            'styles/app.scss'          => ['as' => 'style'],
            'js/custom.js'             => ['as' => 'script'],
            'fonts/fa-solid-900.woff2' => ['as' => 'font', 'crossorigin' => 'anonymous'],
        ];

        $links = [];
        foreach ($preloads as $logicalPath => $attrs) {
            $url = $assetMapper->getPublicPath($logicalPath);
            if ($url === null) {
                continue;
            }
            $link = new Link(href: $url);
            foreach ($attrs as $key => $value) {
                $link = $link->withAttribute($key, $value);
            }
            $links[] = $link;
        }

        return $links !== [] ? $this->sendEarlyHints($links) : new Response();
    }
}
