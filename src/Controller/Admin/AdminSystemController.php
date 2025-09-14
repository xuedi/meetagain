<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\ConfigType;
use App\Form\SettingsType;
use App\Repository\ConfigRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\ImageRepository;
use App\Service\ConfigService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminSystemController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageRepository $imageRepo,
        private readonly ConfigRepository $configRepo,
        private readonly ConfigService $configService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/admin/system', name: 'app_admin_system')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveForm($form->getData());
        }

        return $this->render('admin/system/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'config' => $this->configRepo->findBy(['type' => ConfigType::Boolean]),
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

    #[Route('/admin/system/boolean/{name}', name: 'app_admin_system_boolean')]
    public function boolean(Request $request, string $name): Response
    {
        $config = $this->configRepo->findOneBy(['name' => $name]);
        $value = $config->getValue() !== 'true';
        $config->setValue($value ? 'true' : 'false');

        $this->em->persist($config);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $value]);
        }

        return $this->redirectToRoute('app_admin_system');
    }
}
