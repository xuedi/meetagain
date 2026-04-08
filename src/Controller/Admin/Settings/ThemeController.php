<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Form\ThemeColorsType;
use App\Service\Admin\CommandService;
use App\Service\Config\ConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/theme')]
final class ThemeController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ConfigService $configService,
        private readonly CommandService $commandService,
    ) {}

    #[Route('', name: 'app_admin_system_theme', methods: ['GET', 'POST'])]
    public function theme(Request $request): Response
    {
        $colorsForm = $this->createForm(ThemeColorsType::class);
        $colorsForm->handleRequest($request);

        if ($colorsForm->isSubmitted() && $colorsForm->isValid()) {
            $this->configService->saveColors($colorsForm->getData());
            $this->commandService->rebuildTheme();
            $this->addFlash('success', 'Theme colors saved and CSS rebuilt');
        }

        return $this->render('admin/system/theme/index.html.twig', [
            'active' => 'system',
            'colorsForm' => $colorsForm,
        ]);
    }
}
