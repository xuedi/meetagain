<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use App\Publisher\PluginSettings\PluginSettingsResolver;
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
        private readonly PluginSettingsResolver $resolver,
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

        $descriptors = $this->pluginSettingsService->getProviders();

        if ($request->isMethod('POST')) {
            $key = $request->query->get('provider', '');
            $descriptor = $this->pluginSettingsService->getProvider($key);
            if ($descriptor === null) {
                throw $this->createNotFoundException();
            }

            $data = $this->loadGlobal($descriptor);
            $form = $this->buildForm($descriptor, $data);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $descriptor->applyForm($data, $form);
                $this->resolver->resolveStore($key, null)?->save($key, $data, null);
                $this->addFlash('success', 'admin_system_plugin_settings.flash_saved');

                return $this->redirectToRoute('app_admin_plugin_settings');
            }

            $forms = [];
            foreach ($descriptors as $descriptorKey => $other) {
                $forms[$descriptorKey] = $descriptorKey === $key ? $form->createView() : $this->buildForm($other, $this->loadGlobal($other))->createView();
            }
        } else {
            $forms = [];
            foreach ($descriptors as $descriptorKey => $descriptor) {
                $forms[$descriptorKey] = $this->buildForm($descriptor, $this->loadGlobal($descriptor))->createView();
            }
        }

        $adminTop = new AdminTop(info: [], actions: [
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: $this->generateUrl('app_admin_plugin'), icon: 'arrow-left'),
        ]);

        return $this->render('admin/system/plugin_settings/index.html.twig', [
            'providers' => $descriptors,
            'forms' => $forms,
            'adminTop' => $adminTop,
            'active' => 'plugin',
        ]);
    }

    private function loadGlobal(PluginSettingsDescriptorInterface $descriptor): object
    {
        $key = $descriptor->getKey();

        return $this->resolver->resolveStore($key, null)?->load($key, null) ?? $descriptor->createDefault();
    }

    private function buildForm(PluginSettingsDescriptorInterface $descriptor, object $data): FormInterface
    {
        return $this->createForm($descriptor->getFormType(), $data, $descriptor->getFormOptions($data));
    }
}
