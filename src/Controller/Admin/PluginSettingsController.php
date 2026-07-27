<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\Resolver;
use App\Service\Admin\PluginSettingsService;
use App\Service\Config\PluginService;
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
        private readonly PluginService $pluginService,
        private readonly Resolver $resolver,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('/admin/plugin/{key}/settings', name: 'app_admin_plugin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, string $key): Response
    {
        $descriptors = $this->pluginSettingsService->getByPlugin($key);
        if ($descriptors === []) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $sectionKey = $request->query->get('section', '');
            $descriptor = $descriptors[$sectionKey] ?? null;
            if ($descriptor === null) {
                throw $this->createNotFoundException();
            }

            $data = $this->loadGlobal($descriptor);
            $form = $this->buildForm($descriptor, $data);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $descriptor->applyForm($data, $form);
                $this->resolver->resolveStore($sectionKey, null)?->save($sectionKey, $data, null);
                $this->addFlash('success', 'admin_system_plugin_settings.flash_saved');

                return $this->redirectToRoute('app_admin_plugin_settings', ['key' => $key]);
            }

            $forms = [];
            foreach ($descriptors as $descriptorKey => $other) {
                $forms[$descriptorKey] = $descriptorKey === $sectionKey ? $form->createView() : $this->buildForm($other, $this->loadGlobal($other))->createView();
            }
        } else {
            $forms = [];
            foreach ($descriptors as $descriptorKey => $descriptor) {
                $forms[$descriptorKey] = $this->buildForm($descriptor, $this->loadGlobal($descriptor))->createView();
            }
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoText($this->pluginService->getName($key)),
        ], actions: [
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: $this->generateUrl('app_admin_plugin'), icon: 'arrow-left'),
        ]);

        return $this->render('admin/system/plugin_settings/index.html.twig', [
            'pluginKey' => $key,
            'providers' => $descriptors,
            'forms' => $forms,
            'adminTop' => $adminTop,
            'active' => 'plugin',
        ]);
    }

    private function loadGlobal(DescriptorInterface $descriptor): object
    {
        $key = $descriptor->getKey();

        return $this->resolver->resolveStore($key, null)?->load($key, null) ?? $descriptor->createDefault();
    }

    private function buildForm(DescriptorInterface $descriptor, object $data): FormInterface
    {
        return $this->createForm($descriptor->getFormType(), $data, $descriptor->getFormOptions($data));
    }
}
