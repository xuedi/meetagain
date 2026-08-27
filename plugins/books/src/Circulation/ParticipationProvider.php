<?php declare(strict_types=1);

namespace Plugin\Books\Circulation;

use App\Circulation\ParticipationProviderInterface;
use Override;
use Plugin\Books\Service\BookService;
use Plugin\Books\Service\ConfigService;

final readonly class ParticipationProvider implements ParticipationProviderInterface
{
    public function __construct(
        private ConfigService $config,
    ) {}

    #[Override]
    public function isEnabled(string $itemType): ?bool
    {
        if ($itemType !== BookService::ITEM_TYPE) {
            return null;
        }

        return $this->config->getConfig()->isCirculation();
    }
}
