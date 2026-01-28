<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\Form\SettingsType;
use App\Form\ThemeColorsType;
use App\Service\ConfigService;
use App\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SystemController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly ConfigService $configService,
    ) {}

    public function index(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveForm($form->getData());
            $this->addFlash('success', 'Settings saved');
        }

        $colorsForm = $this->createForm(ThemeColorsType::class);
        $colorsForm->handleRequest($request);

        if ($colorsForm->isSubmitted() && $colorsForm->isValid()) {
            $this->configService->saveColors($colorsForm->getData());
            $this->addFlash('success', 'Theme colors saved');
        }

        return $this->render('admin_modules/system/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'colorsForm' => $colorsForm,
            'colorDefaults' => $this->configService->getThemeColorDefaults(),
            'config' => $this->configService->getBooleanConfigs(),
        ]);
    }

    public function regenerateThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->regenerateAllThumbnails();
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Regenerated thumbnails for ' . $cnt . ' images in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system');
    }

    public function cleanupThumbnails(): Response
    {
        $startTime = microtime(true);
        $cnt = $this->imageService->deleteObsoleteThumbnails();
        $executionTime = microtime(true) - $startTime;

        $this->addFlash('success', 'Deleted ' . $cnt . ' obsolete thumbnail in ' . $executionTime . ' seconds');

        return $this->redirectToRoute('app_admin_system');
    }

    public function boolean(Request $request, string $name): Response
    {
        $value = $this->configService->toggleBoolean($name);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $value]);
        }

        return $this->redirectToRoute('app_admin_system');
    }
}
