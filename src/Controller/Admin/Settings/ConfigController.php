<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;
use App\Form\SettingsType;
use App\Service\ConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
class ConfigController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[Route('', name: 'app_admin_system')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_system_config');
    }

    #[Route('/config', name: 'app_admin_system_config', methods: ['GET', 'POST'])]
    public function config(Request $request): Response
    {
        $form = $this->createForm(SettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveForm($form->getData());
            $this->addFlash('success', 'Settings saved');
        }

        return $this->render('admin/system/config/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'config' => $this->configService->getBooleanConfigs(),
        ]);
    }

    #[Route('/boolean/{name}', name: 'app_admin_system_boolean', methods: ['POST'])]
    public function boolean(Request $request, string $name): Response
    {
        $value = $this->configService->toggleBoolean($name);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $value]);
        }

        return $this->redirectToRoute('app_admin_system_config');
    }
}
