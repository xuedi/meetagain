<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\TrustBand;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustInterface;
use Module\Trust\Contract\TrustLevel;
use Override;

final readonly class TrustService implements TrustInterface
{
    public function __construct(
        private ScoreProvider $scoreProvider,
        private ConfigStore $configStore,
        private VouchService $vouchService,
        private AccessResolver $accessResolver,
    ) {}

    #[Override]
    public function getScore(string $context, int $userId): int
    {
        return $this->scoreProvider->getMap($context)[$userId] ?? 0;
    }

    #[Override]
    public function getScores(string $context): array
    {
        $map = $this->scoreProvider->getMap($context);
        $viewerId = $this->accessResolver->getViewerId();

        if ($viewerId !== null && $this->accessResolver->canAdminister($context, $viewerId)) {
            return $map;
        }

        return $viewerId !== null ? [$viewerId => $map[$viewerId] ?? 0] : [];
    }

    #[Override]
    public function getBand(string $context, int $userId): TrustBand
    {
        return $this->configStore->get($context)->bandFor($this->getScore($context, $userId));
    }

    #[Override]
    public function getVouchCount(string $context, int $userId): int
    {
        return $this->vouchService->getVouchCounts($context)[$userId] ?? 0;
    }

    #[Override]
    public function meetsMinimum(string $context, int $userId): bool
    {
        return $this->getScore($context, $userId) >= $this->configStore->get($context)->minimumToParticipate;
    }

    #[Override]
    public function getConfig(string $context): TrustConfig
    {
        return $this->configStore->get($context);
    }

    #[Override]
    public function grant(string $context, int $fromUserId, int $toUserId, TrustLevel $level): void
    {
        $this->vouchService->grant($context, $fromUserId, $toUserId, $level);
    }

    #[Override]
    public function revoke(string $context, int $fromUserId, int $toUserId): void
    {
        $this->vouchService->revoke($context, $fromUserId, $toUserId);
    }

    #[Override]
    public function getOutgoing(string $context, int $fromUserId): array
    {
        return $this->vouchService->getOutgoing($context, $fromUserId);
    }
}
