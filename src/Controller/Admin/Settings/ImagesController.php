<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/images')]
final class ImagesController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepository,
        private readonly ImageLocationRepository $imageLocationRepository,
        private readonly ImageLocationService $imageLocationService,
    ) {}

    #[Route('', name: 'app_admin_system_images', methods: ['GET'])]
    public function images(): Response
    {
        return $this->render('admin/system/images/index.html.twig', [
            'active' => 'system',
            'images' => $this->imageRepository->findAll(),
            'usageCounts' => $this->imageLocationRepository->countPerImageId(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_system_images_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $image = $this->imageRepository->find($id);
        if ($image === null) {
            throw $this->createNotFoundException('Image not found');
        }

        $locations = $this->imageLocationRepository->findByImageId($id);

        $editLinks = [];
        foreach ($locations as $location) {
            $editLinks[$location->getId()] = $this->imageLocationService->resolveEditLink($location);
        }

        return $this->render('admin/system/images/show.html.twig', [
            'active' => 'system',
            'image' => $image,
            'locations' => $locations,
            'editLinks' => $editLinks,
        ]);
    }

    #[Route('/regenerate_thumbnails', name: 'app_admin_regenerate_thumbnails', methods: ['POST'])]
    public function regenerateThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->regenerateAllThumbnails();
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Regenerated thumbnails for ' . $cnt . ' images in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/cleanup_thumbnails', name: 'app_admin_cleanup_thumbnails', methods: ['POST'])]
    public function cleanupThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->deleteObsoleteThumbnails();
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Deleted ' . $cnt . ' obsolete thumbnail in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system_images');
    }

    #[Route('/sync_locations', name: 'app_admin_sync_image_locations', methods: ['POST'])]
    public function syncLocations(): Response
    {
        $this->imageLocationService->discover();
        $this->addFlash('success', 'Image location index has been synchronised.');

        return $this->redirectToRoute('app_admin_system_images');
    }
}
