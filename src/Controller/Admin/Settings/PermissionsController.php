<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Service\Admin\PermissionInspectorService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/permissions')]
final class PermissionsController extends AbstractAdminController
{
    public function __construct(
        private readonly PermissionInspectorService $inspector,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_system_permissions', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/system/permissions/index.html.twig', [
            'active'        => 'system',
            'activeSection' => 'permissions',
            'groups'        => $this->inspector->getEntriesGroupedByRole(),
            'roleOrder'     => $this->inspector->getRoleDisplayOrder(),
        ]);
    }
}
