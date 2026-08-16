<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Publisher\Frontpage\FrontpageProviderInterface;
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
        private readonly RouterInterface $router,
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

        return new RedirectResponse($this->router->generate('app_default'));
    }

    #[Route('/install', name: 'app_install')]
    public function install(): Response
    {
        return $this->redirect('/');
    }
}
