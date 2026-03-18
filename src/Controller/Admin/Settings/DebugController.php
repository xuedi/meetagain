<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/debug')]
final class DebugController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_system_debug', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/system/debug/index.html.twig', [
            'active' => 'system',
            'activeSection' => 'debug',
        ]);
    }

    #[Route('/trigger-exception', name: 'app_admin_system_debug_trigger', methods: ['POST'])]
    public function triggerException(): never
    {
        throw new \RuntimeException('Bugsink test exception triggered from MeetAgain admin debug panel.');
    }
}
