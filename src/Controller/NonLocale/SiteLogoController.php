<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Service\Media\SiteLogoPngRenderer;
use App\Service\Media\SiteLogoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SiteLogoController extends AbstractController
{
    public function __construct(
        private readonly SiteLogoPngRenderer $renderer,
    ) {}

    #[Route(SiteLogoResolver::ENDPOINT_PATH, name: 'app_site_logo', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $logo = $this->renderer->render() ?? throw new NotFoundHttpException('This site has no logo to serve');

        $response = new Response($logo['content'], Response::HTTP_OK, ['Content-Type' => 'image/png']);
        $response->setEtag($logo['etag']);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->isNotModified($request);

        return $response;
    }
}
