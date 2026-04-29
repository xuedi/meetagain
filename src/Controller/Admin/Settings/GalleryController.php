<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/component-gallery')]
final class GalleryController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_system_gallery', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/_gallery/index.html.twig', [
            'active' => 'system',
        ]);
    }
}
