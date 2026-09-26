<?php declare(strict_types=1);

namespace Plugin\Boardgames\Circulation;

use Module\Circulation\Contract\TrustEnabledProviderInterface;
use Override;
use Plugin\Boardgames\Service\ConfigService;
use Plugin\Boardgames\Service\GameService;

final readonly class TrustProvider implements TrustEnabledProviderInterface
{
    public function __construct(
        private ConfigService $config,
    ) {}

    #[Override]
    public function isTrustEnabled(string $itemType): ?bool
    {
        if ($itemType !== GameService::ITEM_TYPE) {
            return null;
        }

        return $this->config->getConfig()->isTrustActive();
    }
}
