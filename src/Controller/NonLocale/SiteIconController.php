<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Service\Media\SiteIconResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SiteIconController extends AbstractController
{
    public function __construct(
        private readonly SiteIconResolver $resolver,
    ) {}

    #[Route(SiteIconResolver::ENDPOINT_PATH, name: 'app_site_icon', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $icon = $this->resolver->renderPng() ?? throw new NotFoundHttpException('This site has no icon to serve');

        $response = new Response($icon['content'], Response::HTTP_OK, ['Content-Type' => 'image/png']);
        $response->setEtag($icon['etag']);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->isNotModified($request);

        return $response;
    }
}
