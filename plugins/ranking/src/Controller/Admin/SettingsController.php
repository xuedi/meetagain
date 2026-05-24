<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller\Admin;

use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Publisher\PluginSettings\PluginSettingsLinkProviderInterface;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Form\RankingConfigType;
use Plugin\Ranking\Service\GroupContextResolver;
use Plugin\Ranking\Service\PluginDataResetService;
use Plugin\Ranking\Service\RankingConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_STEWARD')]
final class SettingsController extends AbstractController implements PluginSettingsLinkProviderInterface
{
    public function __construct(
        private readonly RankingConfigService $configService,
        private readonly PluginDataResetService $resetService,
        private readonly GroupContextResolver $groupContext,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getPluginKey(): string
    {
        return 'ranking';
    }

    public function getRoute(): string
    {
        return 'app_plugin_ranking_admin_settings';
    }

    public function getLabelKey(): string
    {
        return 'ranking_admin_settings.page_title';
    }

    public function getIcon(): ?string
    {
        return 'trophy';
    }

    #[Route('/admin/plugin/ranking/settings', name: 'app_plugin_ranking_admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $config = $this->configService->getOrCreateForCurrentGroup();
        $originalArchetype = $config->getArchetype();

        $form = $this->createForm(RankingConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->save($config);
            $this->addFlash('success', 'ranking_admin_settings.flash_saved');
            if ($originalArchetype !== $config->getArchetype()) {
                $this->addFlash('warning', 'ranking_admin_settings.flash_archetype_change_warning');
            }

            return $this->redirectToRoute('app_plugin_ranking_admin_settings');
        }

        return $this->render('@Ranking/admin/settings/index.html.twig', [
            'form' => $form->createView(),
            'config' => $config,
            'groupName' => $this->groupContext->getCurrentGroupName(),
            'adminTop' => $this->buildAdminTop(),
            'active' => 'plugins',
        ]);
    }

    #[Route('/admin/plugin/ranking/reset', name: 'app_plugin_ranking_admin_reset', methods: ['POST'])]
    public function resetGroupData(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ranking_admin_reset', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'global.csrf_invalid');

            return $this->redirectToRoute('app_plugin_ranking_admin_settings');
        }

        $typed = trim((string) $request->request->get('confirm_name', ''));
        $expected = $this->groupContext->getCurrentGroupName();
        if ($typed === '' || $typed !== $expected) {
            $this->addFlash('warning', 'ranking_admin_settings.flash_reset_name_mismatch');

            return $this->redirectToRoute('app_plugin_ranking_admin_settings');
        }

        $this->resetService->resetGroupData($this->groupContext->getCurrentGroupId());
        $this->addFlash('success', 'ranking_admin_settings.flash_reset_done');

        return $this->redirectToRoute('app_plugin_ranking_admin_settings');
    }

    private function buildAdminTop(): AdminTop
    {
        return new AdminTop(info: [], actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_group_plugins'),
                icon: 'arrow-left',
            ),
            new AdminTopActionButton(
                label: $this->translator->trans('ranking_admin_rank_definition.page_title'),
                target: $this->generateUrl('app_plugin_ranking_admin_definitions'),
                icon: 'list',
            ),
        ]);
    }
}
