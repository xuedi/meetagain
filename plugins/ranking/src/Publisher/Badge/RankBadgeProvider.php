<?php declare(strict_types=1);

namespace Plugin\Ranking\Publisher\Badge;

use App\Entity\UserBadge;
use App\UserBadgeProviderInterface;
use Plugin\Ranking\Repository\MemberRankRepository;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\GroupContextResolver;
use Plugin\Ranking\Service\RankingConfigService;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class RankBadgeProvider implements UserBadgeProviderInterface
{
    public function __construct(
        private RankingConfigService $configService,
        private MemberRankRepository $memberRankRepository,
        private RankDefinitionRepository $definitionRepository,
        private GroupContextResolver $groupContext,
        private TranslatorInterface $translator,
    ) {}

    public function getBadges(int $userId): array
    {
        $config = $this->configService->findForCurrentGroup();
        if ($config === null || !$config->isShowBadge()) {
            return [];
        }

        $rank = $this->memberRankRepository->findForUserAndGroup($userId, $this->groupContext->getCurrentGroupId());
        if ($rank === null) {
            return [];
        }

        if ($config->getArchetype()->isNumeric()) {
            if ($rank->getNumericValue() === null) {
                return [];
            }

            return [
                new UserBadge(
                    icon: 'fa-solid fa-trophy',
                    title: (string) $rank->getNumericValue(),
                    color: 'has-text-info',
                ),
            ];
        }

        if ($rank->getRankDefinitionId() === null) {
            return [];
        }
        $definition = $this->definitionRepository->find($rank->getRankDefinitionId());
        if ($definition === null) {
            return [
                new UserBadge(
                    icon: 'fa-solid fa-circle-question',
                    title: $this->translator->trans('ranking.rank_no_longer_defined'),
                    color: 'has-text-grey',
                ),
            ];
        }

        $title = $definition->getLabelKey() !== null
            ? $this->translator->trans($definition->getLabelKey())
            : $definition->getLabel();

        return [
            new UserBadge(
                icon: 'fa-solid fa-trophy',
                title: $title,
                color: 'has-text-info',
            ),
        ];
    }
}
