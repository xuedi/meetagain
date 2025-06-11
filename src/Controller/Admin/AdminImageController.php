<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminImageController extends AbstractController
{
    #[Route('/admin/image/', name: 'app_admin_image')]
    public function imageList(ImageRepository $repo): Response
    {
        return $this->render('admin/image/list.html.twig', [
            'active' => 'image',
            'images' => $repo->findAll(),
        ]);
    }
}
