<?php declare(strict_types=1);

namespace Plugin\AdminTables\Controller;

use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ImageController extends AbstractController
{
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
