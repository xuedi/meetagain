<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Service\Media\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ImageController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    #[Route('/profile/images/{action}/{id}/{imageId}', name: 'app_profile_images', requirements: [
        'action' => 'profile|event',
    ])]
    public function images($action = 'profile', ?int $id = null, ?int $imageId = null): Response
    {
        $image = null;
        $imageList = null;
        switch ($action) {
            case 'profile':
                $image = $this->getAuthedUser()->getImage();
                break;
            case 'event':
                $imageList = $this->imageService->findEventUploads($this->getAuthedUser(), $id);
                $image = $imageId === null ? null : $this->imageService->findImage($imageId);
                break;
        }

        return $this->render('profile/images.html.twig', [
            'action' => $action,
            'id' => $id,
            'image' => $image,
            'imageList' => $imageList,
            'eventList' => $this->imageService->getEventList($this->getAuthedUser()),
        ]);
    }
}
