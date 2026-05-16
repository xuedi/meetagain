<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use Plugin\Filmclub\Enum\ViewType;
use Plugin\Filmclub\Service\ViewTypeResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/filmclub')]
final class ViewController extends AbstractController
{
    public function __construct(
        private readonly ViewTypeResolver $viewTypeResolver,
    ) {}

    #[Route('/view/{context}/{type}', name: 'app_plugin_filmclub_set_view', methods: ['GET'])]
    public function setView(string $context, ViewType $type, Request $request): RedirectResponse
    {
        $this->viewTypeResolver->set($context, $type);

        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_filmclub_filmlist');
    }
}
