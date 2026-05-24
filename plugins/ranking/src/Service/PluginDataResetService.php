<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use App\Activity\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Ranking\Activity\Messages\PluginDataReset;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Repository\RankHistoryRepository;
use Plugin\Ranking\Repository\RankingConfigRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class PluginDataResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RankingConfigRepository $configRepository,
        private RankDefinitionRepository $definitionRepository,
        private MemberRankRepository $memberRankRepository,
        private RankHistoryRepository $historyRepository,
        private ActivityService $activityService,
        private Security $security,
    ) {}

    public function resetGroupData(int $groupId): void
    {
        $this->entityManager->wrapInTransaction(function () use ($groupId): void {
            $this->historyRepository->deleteAllForGroup($groupId);
            $this->memberRankRepository->deleteAllForGroup($groupId);

            $config = $this->configRepository->findByGroup($groupId);
            if ($config !== null) {
                $this->definitionRepository->deleteAllForConfig($config);
                $this->configRepository->remove($config, true);
            }
        });

        $user = $this->security->getUser();
        if ($user !== null && method_exists($user, 'getId')) {
            $this->activityService->log(PluginDataReset::TYPE, $user, ['group_id' => $groupId]);
        }
    }
}
