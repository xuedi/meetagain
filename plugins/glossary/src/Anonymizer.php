<?php declare(strict_types=1);

namespace Plugin\Glossary;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use Override;

readonly class Anonymizer implements AnonymizerInterface
{
    #[Override]
    public function getOrder(): int
    {
        return 40;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('plg_glossary_glossary', kept: ['phrase', 'secondary', 'term_language']),
            Claim::columns('plg_glossary_definition', kept: ['language', 'text']),
            Claim::columns('plg_glossary_trainer_card'),
            Claim::columns('plg_glossary_trainer_day'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
