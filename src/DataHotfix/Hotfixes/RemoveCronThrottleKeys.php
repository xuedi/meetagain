<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Service\AppStateService;
use Override;

readonly class RemoveCronThrottleKeys implements DataHotfixInterface
{
    private const array KEYS = [
        'circulation.maintenance_last_run',
    ];

    public function __construct(
        private AppStateService $appState,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_10_19_remove_cron_throttle_keys';
    }

    #[Override]
    public function execute(): void
    {
        foreach (self::KEYS as $key) {
            $this->appState->remove($key);
        }
    }
}
