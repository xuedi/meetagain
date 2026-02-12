<?php declare(strict_types=1);

namespace Plugin\AdminTables\Controller;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOUNDER')]
class ImageController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return AdminNavigationConfig::single(
            section: 'Tables',
            label: 'menu_admin_image',
            route: 'app_admin_image',
            active: 'image',
        );
    }

    public function __construct(
        private readonly ImageRepository $repo,
    ) {}

    #[Route('/admin/image', name: 'app_admin_image')]
    public function imageList(): Response
    {
        return $this->render('@AdminTables/tables/image_list.html.twig', [
            'active' => 'image',
            'images' => $this->repo->findAll(),
        ]);
    }
}
