<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\TrustBand;
use Module\Trust\Contract\TrustLevel;

final readonly class RowBuilder
{
    public function __construct(
        private ScoreProvider $scoreProvider,
        private ConfigStore $configStore,
        private VouchService $vouchService,
        private MemberNameResolver $names,
    ) {}

    /**
     * @return list<array{userId: int, name: string, band: TrustBand, vouches: int, score: int|null, own: TrustLevel|null, self: bool}>
     */
    public function build(string $context, int $viewerId, bool $viewerIsAdministrator): array
    {
        $map = $this->scoreProvider->getMap($context);
        $config = $this->configStore->get($context);
        $counts = $this->vouchService->getVouchCounts($context);
        $outgoing = $this->vouchService->getOutgoing($context, $viewerId);
        $names = $this->names->resolve(array_keys($map));

        $rows = [];
        foreach ($map as $userId => $score) {
            $isSelf = $userId === $viewerId;
            $rows[] = [
                'userId' => $userId,
                'name' => $names[$userId] ?? (string) $userId,
                'band' => $config->bandFor($score),
                'vouches' => $counts[$userId] ?? 0,
                'score' => $viewerIsAdministrator || $isSelf ? $score : null,
                'own' => $outgoing[$userId] ?? null,
                'self' => $isSelf,
            ];
        }

        if ($viewerIsAdministrator) {
            usort($rows, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

            return $rows;
        }

        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $rows;
    }
}
