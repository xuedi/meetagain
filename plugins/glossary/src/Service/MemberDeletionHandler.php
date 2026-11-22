<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Override;
use Plugin\Glossary\Repository\TrainerCardRepository;
use Plugin\Glossary\Repository\TrainerDayRepository;

final readonly class MemberDeletionHandler implements EntityActionInterface
{
    public function __construct(
        private TrainerCardRepository $cardRepo,
        private TrainerDayRepository $dayRepo,
    ) {}

    #[Override]
    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if ($action !== EntityAction::EraseUser) {
            return;
        }

        $this->cardRepo->deleteForUser($entityId);
        $this->dayRepo->deleteForUser($entityId);
    }
}
