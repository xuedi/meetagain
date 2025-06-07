<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends AbstractController
{
    public function __construct(private readonly ImageRepository $imageRepo)
    {
    }

    #[Route('/profile/images/{action}/{id}', name: 'app_profile_images')]
    public function images($action = 'profile', ?int $id = null): Response
    {
        $profile = null;
        $imageList = null;
        switch ($action) {
            case 'profile':
                $profile = $this->getAuthedUser()->getImage();
                break;
            case 'event':
                $imageList = $this->imageRepo->findBy(['uploader' => $this->getUser(), 'event' => $id]);
                break;
        }

        dump($imageList);

        return $this->render('profile/images.html.twig', [
            'action' => $action,
            'profile' => $profile,
            'imageList' => $imageList,
            'eventList' => $this->imageRepo->getEventList($this->getUser()),
        ]);
    }
}
