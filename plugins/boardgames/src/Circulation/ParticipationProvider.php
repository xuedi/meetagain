<?php declare(strict_types=1);

namespace Plugin\Boardgames\Circulation;

use App\Circulation\ParticipationProviderInterface;
use Override;
use Plugin\Boardgames\Service\ConfigService;
use Plugin\Boardgames\Service\GameService;

final readonly class ParticipationProvider implements ParticipationProviderInterface
{
    public function __construct(
        private ConfigService $config,
    ) {}

    #[Override]
    public function isEnabled(string $itemType): ?bool
    {
        if ($itemType !== GameService::ITEM_TYPE) {
            return null;
        }

        return $this->config->getConfig()->isCirculation();
    }
}
