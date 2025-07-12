<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Repository\ConfigRepository;
use App\Repository\ImageRepository;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminSystemController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepo,
        private readonly ConfigRepository $configRepo
    )
    {
    }

    #[Route('/admin/system', name: 'app_admin_system')]
    public function index(): Response
    {
        return $this->render('admin/system/index.html.twig', [
            'active' => 'system',
            'config' => $this->configRepo->findAll(),
            'mediaStats' => $this->imageService->getStatistics(),
        ]);
    }

    #[Route('/admin/system/regenerate_thumbnails', name: 'app_admin_regenerate_thumbnails')]
    public function regenerateThumbnails(): Response
    {
        $cnt = 0;
        $startTime = microtime(true);
        foreach ($this->imageRepo->findAll() as $image) {
            $cnt += $this->imageService->createThumbnails($image);
        }
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Regenerated thumbnails for ' . $cnt . ' images in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system');
    }

    #[Route('/admin/system/cleanup_thumbnails', name: 'app_admin_cleanup_thumbnails')]
    public function cleanupThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->deleteObsoleteThumbnails();
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Deleted ' . $cnt . ' obsolete thumbnail in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system');
    }
}
