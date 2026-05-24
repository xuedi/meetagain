<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller;

use App\Entity\User;
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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MyRankController extends AbstractController
{
    public function __construct(
        private readonly RankingConfigService $configService,
        private readonly RankDefinitionRepository $definitionRepository,
        private readonly MemberRankRepository $memberRankRepository,
        private readonly RankAssignmentService $assignmentService,
        private readonly GroupContextResolver $groupContext,
        #[Target('ranking_self_edit')]
        private readonly RateLimiterFactoryInterface $rankingSelfEditLimiter,
    ) {}

    #[Route('/ranking/my-rank', name: 'app_plugin_ranking_my_rank', methods: ['GET', 'POST'])]
    public function myRank(Request $request): Response
    {
        $config = $this->configService->findForCurrentGroup();
        if ($config === null) {
            $this->addFlash('warning', 'ranking_my_rank.plugin_not_enabled');

            return $this->redirectToRoute('app_profile');
        }

        $user = $this->getUser();
        \assert($user instanceof User);
        $userId = (int) $user->getId();
        $groupId = $this->groupContext->getCurrentGroupId();

        $existing = $this->memberRankRepository->findForUserAndGroup($userId, $groupId);
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
            $limiter = $this->rankingSelfEditLimiter->create((string) $userId);
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('warning', 'ranking_my_rank.flash_rate_limited');

                return $this->redirectToRoute('app_plugin_ranking_my_rank');
            }

            if ($config->getArchetype()->isNumeric() && $input->numericValue !== null) {
                $this->assignmentService->assignNumeric($config, $userId, $user, $input->numericValue, RankChangeReason::SelfEdit);
            } elseif ($input->definition instanceof RankDefinition) {
                $this->assignmentService->assignDefinition($config, $userId, $user, $input->definition, RankChangeReason::SelfEdit);
            }

            $this->addFlash('success', 'ranking_my_rank.flash_saved');

            return $this->redirectToRoute('app_plugin_ranking_my_rank');
        }

        return $this->render('@Ranking/my_rank/edit.html.twig', [
            'form' => $form->createView(),
            'config' => $config,
        ]);
    }
}
