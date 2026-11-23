<?php declare(strict_types=1);

namespace Plugin\Karaoke;

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
            Claim::columns('plg_karaoke_song', kept: ['title', 'artist', 'language', 'media_id']),
            Claim::columns('plg_karaoke_lyric_line', kept: ['text']),
            Claim::columns('plg_karaoke_line_translation', kept: ['language', 'text']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
