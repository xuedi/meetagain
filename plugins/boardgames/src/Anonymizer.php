<?php declare(strict_types=1);

namespace Plugin\Boardgames;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Override;

readonly class Anonymizer implements AnonymizerInterface
{
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
        return [
            Claim::columns('plg_boardgames_game', kept: ['name', 'original_name', 'description', 'external_id']),
            Claim::columns('plg_boardgames_ownership', rewritten: ['notes'], kept: ['copy_language']),
            Claim::columns('plg_boardgames_bring_request', rewritten: ['message']),
            Claim::columns('plg_boardgames_pledge'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $context->report(sprintf(
            'texts: %d',
            $this->scrubber->fill('plg_boardgames_ownership', 'notes') + $this->scrubber->fill('plg_boardgames_bring_request', 'message'),
        ));
    }
}
