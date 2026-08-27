<?php declare(strict_types=1);

namespace Plugin\Books\Circulation;

use App\Circulation\Trust\EnabledProviderInterface;
use Override;
use Plugin\Books\Service\BookService;
use Plugin\Books\Service\ConfigService;

final readonly class TrustProvider implements EnabledProviderInterface
{
    public function __construct(
        private ConfigService $config,
    ) {}

    #[Override]
    public function isTrustEnabled(string $itemType): ?bool
    {
        if ($itemType !== BookService::ITEM_TYPE) {
            return null;
        }

        return $this->config->getConfig()->isTrustActive();
    }
}
