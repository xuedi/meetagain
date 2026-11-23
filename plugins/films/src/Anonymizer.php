<?php declare(strict_types=1);

namespace Plugin\Films;

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
            Claim::columns('plg_films_film', kept: ['title', 'original_title', 'external_id', 'description', 'genres']),
            Claim::columns('plg_films_settings', kept: ['encrypted_tmdb_key', 'encrypted_omdb_key']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
