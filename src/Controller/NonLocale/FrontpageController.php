<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Publisher\Frontpage\FrontpageProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Frontpage\ThinPickerLayoutResolver;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

final class FrontpageController extends AbstractController
{
    /**
     * @param iterable<FrontpageProviderInterface> $frontpageProviders
     */
    public function __construct(
        private readonly ConfigService $configService,
        private readonly RouterInterface $router,
        private readonly LanguageService $languageService,
        private readonly ThinPickerLayoutResolver $thinPickerLayoutResolver,
        #[AutowireIterator(FrontpageProviderInterface::class)]
        private readonly iterable $frontpageProviders = [],
    ) {}

    #[Route('/', name: 'app_frontpage')]
    public function index(Request $request): Response
    {
        foreach ($this->frontpageProviders as $provider) {
            $response = $provider->render($request);
            if ($response !== null) {
                return $response;
            }
        }

        if ($this->configService->isShowFrontpage() === false) {
            return new RedirectResponse($this->router->generate('app_default'));
        }

        $languages = $this->languageService->getEnabledLanguages();

        return $this->render('cms/frontpage.html.twig', [
            'languages' => $languages,
            'chrome_disabled' => true,
            'layout' => $this->thinPickerLayoutResolver->resolve(count($languages)),
        ]);
    }

    #[Route('/install', name: 'app_install')]
    public function install(): Response
    {
        return $this->redirect('/');
    }
}
