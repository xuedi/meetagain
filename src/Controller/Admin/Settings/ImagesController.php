<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/images')]
class ImagesController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepository,
    ) {}

    #[Route('', name: 'app_admin_system_images', methods: ['GET'])]
    public function images(): Response
    {
        return $this->render('admin/system/images/index.html.twig', [
            'active' => 'system',
            'images' => $this->imageRepository->findAll(),
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
}
