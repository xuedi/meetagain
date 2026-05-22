<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Publisher\PluginSettings\PluginSettingsProviderInterface;
use App\Service\Admin\PluginSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class PluginSettingsController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly PluginSettingsService $pluginSettingsService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('/admin/plugin/settings', name: 'app_admin_plugin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$this->pluginSettingsService->hasAny()) {
            throw $this->createNotFoundException();
        }

        $providers = $this->pluginSettingsService->getProviders();

        if ($request->isMethod('POST')) {
            $key = $request->query->get('provider', '');
            $provider = $this->pluginSettingsService->getProvider($key);
            if ($provider === null) {
                throw $this->createNotFoundException();
            }

            $data = $provider->loadData();
            $form = $this->buildForm($provider, $data);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $provider->save($data, $form);
                $this->addFlash('success', 'admin_system_plugin_settings.flash_saved');

                return $this->redirectToRoute('app_admin_plugin_settings');
            }

            $forms = [];
            foreach ($providers as $providerKey => $other) {
                $forms[$providerKey] = $providerKey === $key ? $form->createView() : $this->buildForm($other, $other->loadData())->createView();
            }
        } else {
            $forms = [];
            foreach ($providers as $providerKey => $provider) {
                $forms[$providerKey] = $this->buildForm($provider, $provider->loadData())->createView();
            }
        }

        $adminTop = new AdminTop(info: [], actions: [
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: $this->generateUrl('app_admin_plugin'), icon: 'arrow-left'),
        ]);

        return $this->render('admin/system/plugin_settings/index.html.twig', [
            'providers' => $providers,
            'forms' => $forms,
            'adminTop' => $adminTop,
            'active' => 'plugin',
        ]);
    }

    private function buildForm(PluginSettingsProviderInterface $provider, object $data): FormInterface
    {
        return $this->createForm($provider->getFormType(), $data, $provider->getFormOptions());
    }
}
