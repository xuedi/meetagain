<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Service\ConfigService;
use App\Service\LanguageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

class FrontpageController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly RouterInterface $router,
        private readonly LanguageService $languageService,
    ) {
    }

    #[Route('/', name: 'app_frontpage')]
    public function index(): Response
    {
        if ($this->configService->isShowFrontpage() === false) {
            return new RedirectResponse($this->router->generate('app_default'));
        }
        return $this->render('cms/frontpage.html.twig', [
            'languages' => $this->languageService->getAllLanguages(),
        ]);
    }

    #[Route('/install', name: 'app_install')]
    public function install(): Response
    {
        return $this->redirect('/');
    }
}
