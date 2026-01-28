<?php declare(strict_types=1);

namespace App\AdminModules\Tables;

use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ImageController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $repo,
    ) {}

    public function imageList(): Response
    {
        return $this->render('admin_modules/tables/image_list.html.twig', [
            'active' => 'image',
            'images' => $this->repo->findAll(),
        ]);
    }
}
