<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use Plugin\Ranking\Entity\MemberRank;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Repository\RankDefinitionRepository;

readonly class LeaderboardOrderService
{
    public function __construct(
        private RankDefinitionRepository $definitionRepository,
    ) {}

    /**
     * @param list<MemberRank> $ranks
     * @return list<MemberRank>
     */
    public function sort(RankingConfig $config, array $ranks): array
    {
        if ($config->getArchetype()->isNumeric()) {
            usort($ranks, static fn(MemberRank $a, MemberRank $b) => ($b->getNumericValue() ?? 0) <=> ($a->getNumericValue() ?? 0));

            return $ranks;
        }

        $positionsById = [];
        foreach ($this->definitionRepository->findByConfig($config) as $definition) {
            $positionsById[(int) $definition->getId()] = $definition->getPosition();
        }

        usort($ranks, static function (MemberRank $a, MemberRank $b) use ($positionsById): int {
            $aOrphan = $a->getRankDefinitionId() === null || !isset($positionsById[$a->getRankDefinitionId()]);
            $bOrphan = $b->getRankDefinitionId() === null || !isset($positionsById[$b->getRankDefinitionId()]);

            if ($aOrphan !== $bOrphan) {
                return $aOrphan ? 1 : -1;
            }
            if ($aOrphan) {
                return 0;
            }

            return $positionsById[$b->getRankDefinitionId()] <=> $positionsById[$a->getRankDefinitionId()];
        });

        return $ranks;
    }
}
