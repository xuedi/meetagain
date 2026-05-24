<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller\Admin;

use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Form\RankDefinitionType;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\GroupContextResolver;
use Plugin\Ranking\Service\RankDefinitionService;
use Plugin\Ranking\Service\RankingConfigService;
use Plugin\Ranking\ValueObject\ArchetypePresets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/plugin/ranking/definitions')]
final class RankDefinitionController extends AbstractController
{
    public function __construct(
        private readonly RankDefinitionService $definitionService,
        private readonly RankDefinitionRepository $definitionRepository,
        private readonly RankingConfigService $configService,
        private readonly GroupContextResolver $groupContext,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_plugin_ranking_admin_definitions', methods: ['GET'])]
    public function list(): Response
    {
        $config = $this->configService->getOrCreateForCurrentGroup();
        $definitions = $this->definitionRepository->findByConfig($config);
        $presets = $this->definitionService->availablePresets($config);

        return $this->render('@Ranking/admin/rank_definition/list.html.twig', [
            'config' => $config,
            'definitions' => $definitions,
            'presets' => $presets,
            'adminTop' => $this->buildAdminTop(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/new', name: 'app_plugin_ranking_admin_definitions_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $config = $this->configService->getOrCreateForCurrentGroup();
        $definition = new RankDefinition();
        $definition->setConfig($config);
        $definition->setLabel('');

        $form = $this->createForm(RankDefinitionType::class, $definition);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->definitionService->create(
                $config,
                $definition->getLabel(),
                $definition->getColorHex(),
                $definition->getPosition(),
            );
            $this->addFlash('success', 'ranking_admin_rank_definition.flash_created');

            return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
        }

        return $this->render('@Ranking/admin/rank_definition/edit.html.twig', [
            'form' => $form->createView(),
            'adminTop' => $this->buildAdminTop(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_ranking_admin_definitions_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, RankDefinition $definition): Response
    {
        $form = $this->createForm(RankDefinitionType::class, $definition);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->definitionService->update($definition, $definition->getLabel(), $definition->getColorHex(), $definition->getPosition());
            $this->addFlash('success', 'ranking_admin_rank_definition.flash_updated');

            return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
        }

        return $this->render('@Ranking/admin/rank_definition/edit.html.twig', [
            'form' => $form->createView(),
            'adminTop' => $this->buildAdminTop(),
            'active' => 'plugin',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_ranking_admin_definitions_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, RankDefinition $definition): Response
    {
        if (!$this->isCsrfTokenValid('ranking_definition_delete_' . $definition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'global.csrf_invalid');

            return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
        }

        $this->definitionService->delete($definition);
        $this->addFlash('success', 'ranking_admin_rank_definition.flash_deleted');

        return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
    }

    #[Route('/load-preset', name: 'app_plugin_ranking_admin_definitions_load_preset', methods: ['POST'])]
    public function loadPreset(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ranking_preset_load', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'global.csrf_invalid');

            return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
        }

        $config = $this->configService->getOrCreateForCurrentGroup();
        $presetKey = (string) $request->request->get('preset', '');
        $preset = ArchetypePresets::get($presetKey);
        if ($preset === null || $preset->archetype !== $config->getArchetype()) {
            $this->addFlash('warning', 'ranking_admin_rank_definition.flash_preset_incompatible');

            return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
        }

        $this->definitionService->applyPreset($config, $preset);
        $this->addFlash('success', 'ranking_admin_rank_definition.flash_preset_loaded');

        return $this->redirectToRoute('app_plugin_ranking_admin_definitions');
    }

    private function buildAdminTop(): AdminTop
    {
        return new AdminTop(info: [], actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_plugin_ranking_admin_settings'),
                icon: 'arrow-left',
            ),
        ]);
    }
}
