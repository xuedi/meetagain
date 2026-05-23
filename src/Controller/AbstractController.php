<?php declare(strict_types=1);

namespace App\Controller;

use App\AssetMapper\AppBundle;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\WebLink\Link;

abstract class AbstractController extends AbstractSymfonyController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            AssetMapperInterface::class,
            AppBundle::class,
        ]);
    }

    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }

    protected function getResponse(): Response
    {
        /** @var AssetMapperInterface $assetMapper */
        $assetMapper = $this->container->get(AssetMapperInterface::class);

        $preloads = [
            'styles/app.scss' => ['as' => 'style'],
            'fonts/fa-solid-900.woff2' => ['as' => 'font', 'crossorigin' => 'anonymous'],
        ];

        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        if ($request !== null && str_starts_with($request->getLocale(), 'zh')) {
            $preloads['styles/lxgw-wenkai.scss'] = ['as' => 'style'];
        }

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

        // app.js bundle is compiled by MediaCompileCommand — not AssetMapper-managed, use content-hashed path
        $appBundle = $this->container->get(AppBundle::class);
        $links[] = new Link(href: $appBundle->url())->withAttribute('as', 'script');

        return $links !== [] ? $this->sendEarlyHints($links) : new Response();
    }
}
