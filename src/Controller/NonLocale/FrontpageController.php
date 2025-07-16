<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Service\ConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route('/')]
class FrontpageController extends AbstractController
{
    #[Route('/', name: 'app_frontpage')]
    public function index(ConfigService $configService, RouterInterface $router): Response
    {
        if ($configService->isShowFrontpage() === false) {
            return new RedirectResponse($router->generate('app_default'));
        }

        return $this->render('cms/frontpage.html.twig');
    }
}
