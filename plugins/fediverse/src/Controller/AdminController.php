<?php declare(strict_types=1);

namespace Plugin\Fediverse\Controller;

use Plugin\Fediverse\Service\Reconciler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public const string STOP_TOKEN = 'admin_plugin_fediverse_stop';

    public function __construct(
        private readonly Reconciler $reconciler,
    ) {}

    #[Route('/{_locale}/admin/plugin/fediverse/stop', name: 'app_admin_plugin_fediverse_stop', requirements: ['_locale' => '[a-z]{2}'], methods: ['POST'])]
    public function stop(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::STOP_TOKEN, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->reconciler->stop();
        $this->addFlash('success', 'fediverse_config.flash_stopped');

        return $this->redirectToRoute('app_admin_plugin_settings', ['key' => 'fediverse']);
    }
}
