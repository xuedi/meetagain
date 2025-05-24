<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends AbstractController
{
    #[Route('/profile/images', name: 'app_profile_images')]
    public function images(UserRepository $repo): Response
    {
        return $this->render('profile/images.html.twig', [
            //
        ]);
    }
}
