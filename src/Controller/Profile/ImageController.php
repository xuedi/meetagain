<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends AbstractController
{
    public function __construct(
        private readonly ImageRepository $imageRepo,
    ) {
    }

    #[Route('/profile/images/{action}/{id}/{imageId}', name: 'app_profile_images', requirements: [
        'action' => 'profile|event',
    ])]
    public function images($action = 'profile', null|int $id = null, null|int $imageId = null): Response
    {
        $image = null;
        $imageList = null;
        switch ($action) {
            case 'profile':
                $image = $this->getAuthedUser()->getImage();
                break;
            case 'event':
                $imageList = $this->imageRepo->findBy(['uploader' => $this->getUser(), 'event' => $id]);
                $image = $imageId === null ? null : $this->imageRepo->findOneBy(['id' => $imageId]);
                break;
        }

        return $this->render('profile/images.html.twig', [
            'action' => $action,
            'id' => $id,
            'image' => $image,
            'imageList' => $imageList,
            'eventList' => $this->imageRepo->getEventList($this->getAuthedUser()),
        ]);
    }
}
