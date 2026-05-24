<?php declare(strict_types=1);

namespace Plugin\Ranking;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankHistoryRepository;

readonly class UserCleanupHandler implements EntityActionInterface
{
    public function __construct(
        private MemberRankRepository $memberRankRepository,
        private RankHistoryRepository $historyRepository,
    ) {}

    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if ($action !== EntityAction::DeleteUser) {
            return;
        }

        $this->memberRankRepository->deleteAllForUser($entityId);
        $this->historyRepository->deleteAllForUser($entityId);
    }
}
