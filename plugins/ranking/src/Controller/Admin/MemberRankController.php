<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Enum\RankChangeReason;
use Plugin\Ranking\Form\MemberRankType;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\GroupContextResolver;
use Plugin\Ranking\Service\RankAssignmentService;
use Plugin\Ranking\Service\RankingConfigService;
use Plugin\Ranking\ValueObject\MemberRankInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class MemberRankController extends AbstractController
{
    public function __construct(
        private readonly RankingConfigService $configService,
        private readonly RankDefinitionRepository $definitionRepository,
        private readonly MemberRankRepository $memberRankRepository,
        private readonly RankAssignmentService $assignmentService,
        private readonly UserRepository $userRepository,
        private readonly GroupContextResolver $groupContext,
    ) {}

    #[Route('/admin/plugin/ranking/member/{userId}', name: 'app_plugin_ranking_admin_member_rank', methods: ['GET', 'POST'], requirements: ['userId' => '\d+'])]
    public function override(Request $request, int $userId): Response
    {
        $config = $this->configService->findForCurrentGroup();
        if ($config === null) {
            throw $this->createNotFoundException();
        }

        $targetUser = $this->userRepository->find($userId);
        if ($targetUser === null) {
            throw $this->createNotFoundException();
        }

        $actor = $this->getUser();
        \assert($actor instanceof User);

        $existing = $this->memberRankRepository->findForUserAndGroup($userId, $this->groupContext->getCurrentGroupId());
        $input = new MemberRankInput();
        if ($existing !== null) {
            $input->numericValue = $existing->getNumericValue();
            if ($existing->getRankDefinitionId() !== null) {
                $input->definition = $this->definitionRepository->find($existing->getRankDefinitionId());
            }
        }

        $definitions = $this->definitionRepository->findByConfig($config);

        $form = $this->createForm(MemberRankType::class, $input, [
            'archetype' => $config->getArchetype(),
            'definitions' => $definitions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($config->getArchetype()->isNumeric() && $input->numericValue !== null) {
                $this->assignmentService->assignNumeric($config, $userId, $actor, $input->numericValue, RankChangeReason::AdminOverride);
            } elseif ($input->definition instanceof RankDefinition) {
                $this->assignmentService->assignDefinition($config, $userId, $actor, $input->definition, RankChangeReason::AdminOverride);
            }

            $this->addFlash('success', 'ranking_admin_member_rank.flash_saved');

            return $this->redirectToRoute('app_plugin_ranking_admin_member_rank', ['userId' => $userId]);
        }

        return $this->render('@Ranking/admin/member_rank/edit.html.twig', [
            'form' => $form->createView(),
            'config' => $config,
            'targetUser' => $targetUser,
        ]);
    }
}
