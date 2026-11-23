<?php declare(strict_types=1);

namespace Plugin\Dishes;

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
            Claim::columns('plg_dishes_dish', kept: ['phonetic', 'origin']),
            Claim::columns('plg_dishes_dish_translation', kept: ['language', 'name', 'description', 'recipe']),
            Claim::columns('plg_dishes_dish_image'),
            Claim::columns('plg_dishes_dish_like'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
