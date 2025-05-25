<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Repository\ConfigRepository;
use App\Repository\UserRepository;
use App\Service\UploadService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SystemController extends AbstractController
{

    #[Route('/admin/system/config', name: 'app_admin_config')]
    public function configIndex(ConfigRepository $repo): Response
    {
        return $this->render('admin/config.html.twig', [
            'config' => $repo->findAll(),
        ]);
    }

    #[Route('/admin/system/redo-thumbnails', name: 'app_admin_redo_thumbnails')]
    public function thumbnailsIndex(UploadService $upload, UserRepository $userRepo): Response
    {
        $startTime = microtime(true);
        $cnt = 0;
        foreach ($userRepo->findAll() as $user) {
            if ($user === null) {
                continue;
            }
            $upload->createThumbnails($user->getImage(), [[50, 50], [400, 400]]);
            $cnt++;
        }
        // TODO: do for for all images, and get the config from the setting and loop all source
        $executionTime = microtime(true) - $startTime;
        return $this->render('admin/thumbnails.html.twig', [
            'count' => $cnt * 2, // 2 for each user,
            'time' => round($executionTime, 2)
        ]);
    }
}
