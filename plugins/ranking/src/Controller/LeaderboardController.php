<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller;

use App\Repository\UserRepository;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\GroupContextResolver;
use Plugin\Ranking\Service\LeaderboardOrderService;
use Plugin\Ranking\Service\RankingConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    public function __construct(
        private readonly RankingConfigService $configService,
        private readonly MemberRankRepository $memberRankRepository,
        private readonly RankDefinitionRepository $definitionRepository,
        private readonly UserRepository $userRepository,
        private readonly LeaderboardOrderService $orderService,
        private readonly GroupContextResolver $groupContext,
    ) {}

    #[Route('/ranking', name: 'app_plugin_ranking_leaderboard', methods: ['GET'])]
    public function leaderboard(Request $request): Response
    {
        $config = $this->configService->findForCurrentGroup();
        if ($config === null) {
            return $this->render('@Ranking/leaderboard/not_configured.html.twig', [
                'isAdmin' => $this->isGranted('ROLE_ADMIN'),
            ]);
        }

        $groupId = $this->groupContext->getCurrentGroupId();
        $ranks = $this->memberRankRepository->findForLeaderboard($groupId);
        $ranks = $this->orderService->sort($config, $ranks);

        $search = trim((string) $request->query->get('q', ''));
        $userNames = $this->userRepository->getUserNameList();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $ranks = array_values(array_filter($ranks, static function ($r) use ($userNames, $needle): bool {
                $name = $userNames[$r->getUserId()] ?? '';

                return mb_strpos(mb_strtolower((string) $name), $needle) !== false;
            }));
        }

        $definitionsById = [];
        foreach ($this->definitionRepository->findByConfig($config) as $definition) {
            $definitionsById[(int) $definition->getId()] = $definition;
        }

        return $this->render('@Ranking/leaderboard/index.html.twig', [
            'config' => $config,
            'ranks' => $ranks,
            'userNames' => $userNames,
            'definitionsById' => $definitionsById,
            'search' => $search,
        ]);
    }
}
