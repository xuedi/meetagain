<?php declare(strict_types=1);

namespace Plugin\Boardgames\Circulation;

use App\Circulation\Trust\EnabledProviderInterface;
use Override;
use Plugin\Boardgames\Service\ConfigService;
use Plugin\Boardgames\Service\GameService;

final readonly class TrustProvider implements EnabledProviderInterface
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
