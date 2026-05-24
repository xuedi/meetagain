<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Repository\RankingConfigRepository;

readonly class RankingConfigService
{
    public function __construct(
        private RankingConfigRepository $repository,
        private GroupContextResolver $groupContext,
    ) {}

    public function getOrCreateForCurrentGroup(): RankingConfig
    {
        return $this->getOrCreateForGroup($this->groupContext->getCurrentGroupId());
    }

    public function getOrCreateForGroup(int $groupId): RankingConfig
    {
        $existing = $this->repository->findByGroup($groupId);
        if ($existing !== null) {
            return $existing;
        }

        $config = new RankingConfig();
        $config->setGroupId($groupId);
        $this->repository->save($config, true);

        return $config;
    }

    public function findForCurrentGroup(): ?RankingConfig
    {
        return $this->repository->findByGroup($this->groupContext->getCurrentGroupId());
    }

    public function save(RankingConfig $config): void
    {
        $this->repository->save($config, true);
    }
}
