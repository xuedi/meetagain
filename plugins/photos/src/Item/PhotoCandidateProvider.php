<?php declare(strict_types=1);

namespace Plugin\Photos\Item;

use App\Item\CandidateProviderInterface;
use Override;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;

final readonly class PhotoCandidateProvider implements CandidateProviderInterface
{
    public function __construct(
        private ContestService $contestService,
    ) {}

    #[Override]
    public function getCandidateItemIds(string $itemType): array
    {
        if ($itemType !== PhotoService::ITEM_TYPE || !$this->contestService->isLive()) {
            return [];
        }

        return $this->contestService->getQueuedIds();
    }
}
