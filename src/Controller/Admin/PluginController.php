<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Service\Admin\PluginSettingsService;
use App\Service\Config\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/plugin')]
#[IsGranted('ROLE_ADMIN')]
final class PluginController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly PluginService $pluginService,
        private readonly PluginSettingsService $pluginSettingsService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(label: 'admin_shell.menu_plugin', route: 'app_admin_plugin', active: 'plugin', role: 'ROLE_ADMIN'),
            ],
            sectionPriority: 100,
        );
    }

    #[Route('', name: 'app_admin_plugin')]
    public function list(): Response
    {
        $plugins = $this->pluginService->getAdminList();
        $enabledCount = count(array_filter($plugins, static fn(array $plugin): bool => $plugin['enabled']));

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($plugins), $this->translator->trans('admin_system_plugins.summary_installed'))),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%d&nbsp;%s</span>',
                $enabledCount,
                $this->translator->trans('admin_system_plugins.summary_enabled'),
            )),
        ];

        return $this->render('admin/system/plugin_list.html.twig', [
            'plugins' => $plugins,
            'configurableKeys' => $this->pluginSettingsService->getConfigurablePluginKeys(),
            'active' => 'plugin',
            'adminTop' => new AdminTop(info: $info),
        ]);
    }

    #[Route('/{key}/toggle', name: 'app_admin_plugin_toggle', requirements: ['key' => '[a-z0-9_]+'], methods: ['POST'])]
    public function toggle(string $key, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_admin_plugin_toggle' . $key, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if (!$this->pluginService->isInstalled($key) || $this->pluginService->isSystem($key)) {
            throw $this->createNotFoundException();
        }

        $enable = !$this->pluginService->isEnabled($key);
        $blockers = $enable ? $this->pluginService->missingRequirements($key) : $this->pluginService->activeDependents($key);
        if ($blockers !== []) {
            $names = implode(', ', array_map($this->pluginService->getName(...), $blockers));
            $this->addFlash('error', $this->translator->trans($enable ? 'admin_system_plugins.flash_requires' : 'admin_system_plugins.flash_required_by', [
                '%plugin%' => $this->pluginService->getName($key),
                '%plugins%' => $names,
            ]));

            return $this->redirectToRoute('app_admin_plugin');
        }

        $this->pluginService->toggle($key, $enable);

        return $this->redirectToRoute('app_admin_plugin');
    }
}
