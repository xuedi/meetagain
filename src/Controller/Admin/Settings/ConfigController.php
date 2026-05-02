<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Form\SettingsType;
use App\Service\Config\ConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class ConfigController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ConfigService $configService,
    ) {
        parent::__construct($translator, 'config');
    }

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
            $this->addFlash('success', $this->translator->trans('admin_system_config.flash_saved'));
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_system_config.intro'))],
        );

        return $this->render('admin/system/config/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'config' => $this->configService->getBooleanConfigs(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
