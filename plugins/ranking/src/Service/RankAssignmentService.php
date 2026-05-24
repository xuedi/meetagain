<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Plugin\Ranking\Activity\Messages\MemberRankChanged;
use Plugin\Ranking\Entity\MemberRank;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Entity\RankHistory;
use Plugin\Ranking\Enum\RankChangeReason;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankHistoryRepository;

readonly class RankAssignmentService
{
    public function __construct(
        private MemberRankRepository $memberRankRepository,
        private RankHistoryRepository $historyRepository,
        private UserRepository $userRepository,
        private ActivityService $activityService,
    ) {}

    public function assignNumeric(
        RankingConfig $config,
        int $userId,
        User $actor,
        int $value,
        RankChangeReason $reason,
    ): MemberRank {
        $rank = $this->memberRankRepository->findForUserAndGroup($userId, $config->getGroupId());
        $oldValue = $rank?->getNumericValue();

        if ($oldValue === $value) {
            return $rank ?? $this->buildNewRank($config, $userId);
        }

        if ($rank === null) {
            $rank = $this->buildNewRank($config, $userId);
        }
        $rank->setNumericValue($value);
        $rank->setRankDefinitionId(null);
        $rank->setUpdatedAt(new DateTimeImmutable());
        $this->memberRankRepository->save($rank, true);

        $this->writeHistoryAndActivity(
            $config,
            $userId,
            $actor,
            $reason,
            $oldValue === null ? null : (string) $oldValue,
            (string) $value,
        );

        return $rank;
    }

    public function assignDefinition(
        RankingConfig $config,
        int $userId,
        User $actor,
        RankDefinition $definition,
        RankChangeReason $reason,
    ): MemberRank {
        $rank = $this->memberRankRepository->findForUserAndGroup($userId, $config->getGroupId());
        $oldId = $rank?->getRankDefinitionId();

        if ($oldId === $definition->getId()) {
            return $rank ?? $this->buildNewRank($config, $userId);
        }

        if ($rank === null) {
            $rank = $this->buildNewRank($config, $userId);
        }
        $rank->setRankDefinitionId($definition->getId());
        $rank->setNumericValue(null);
        $rank->setUpdatedAt(new DateTimeImmutable());
        $this->memberRankRepository->save($rank, true);

        $this->writeHistoryAndActivity(
            $config,
            $userId,
            $actor,
            $reason,
            $oldId === null ? null : (string) $oldId,
            $definition->getLabel(),
        );

        return $rank;
    }

    private function writeHistoryAndActivity(
        RankingConfig $config,
        int $userId,
        User $actor,
        RankChangeReason $reason,
        ?string $oldValue,
        ?string $newValue,
    ): void {
        $history = new RankHistory();
        $history->setUserId($userId);
        $history->setGroupId($config->getGroupId());
        $history->setActorUserId((int) $actor->getId());
        $history->setReason($reason);
        $history->setOldValue($oldValue);
        $history->setNewValue($newValue);
        $this->historyRepository->save($history, true);

        if ($reason === RankChangeReason::Import) {
            return;
        }

        $this->activityService->log(MemberRankChanged::TYPE, $actor, [
            'group_id' => $config->getGroupId(),
            'user_id' => $userId,
            'reason' => $reason->value,
            'old' => $oldValue ?? '-',
            'new' => $newValue ?? '-',
        ]);
    }

    private function buildNewRank(RankingConfig $config, int $userId): MemberRank
    {
        $rank = new MemberRank();
        $rank->setUserId($userId);
        $rank->setGroupId($config->getGroupId());

        return $rank;
    }
}
