<?php declare(strict_types=1);

namespace Plugin\Fediverse;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Override;

readonly class Anonymizer implements AnonymizerInterface
{
    private const array TABLES = [
        'plg_fediverse_pending_event',
        'plg_fediverse_publication',
        'plg_fediverse_delivery',
        'plg_fediverse_follower',
        'plg_fediverse_account',
    ];

    public function __construct(
        private Scrubber $scrubber,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 40;
    }

    #[Override]
    public function getClaims(): array
    {
        return array_map(Claim::emptied(...), self::TABLES);
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $rows = 0;
        foreach (self::TABLES as $table) {
            $rows += $this->scrubber->empty($table);
        }

        $context->report(sprintf('fediverse publications, followers, deliveries and accounts deleted: %d', $rows));
    }
}
