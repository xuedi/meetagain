<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

final class FrontpageController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly RouterInterface $router,
        private readonly LanguageService $languageService,
    ) {}

    #[Route('/', name: 'app_frontpage')]
    public function index(): Response
    {
        if ($this->configService->isShowFrontpage() === false) {
            return new RedirectResponse($this->router->generate('app_default'));
        }

        return $this->render('cms/frontpage.html.twig', [
            'languages' => $this->languageService->getEnabledLanguages(),
        ]);
    }

    #[Route('/install', name: 'app_install')]
    public function install(): Response
    {
        return $this->redirect('/');
    }
}
