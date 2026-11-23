<?php declare(strict_types=1);

namespace Plugin\Photos;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Override;
use Plugin\Photos\Activity\Messages\CommentedOnPhoto;
use Plugin\Photos\Activity\Messages\PhotoAdded;

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
            Claim::columns('plg_photos_photo', cleared: ['meta']),
            Claim::columns('plg_photos_photo_translation', rewritten: ['title', 'description'], kept: ['language']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $this->scrubber->clear('plg_photos_photo', 'meta');
        $texts = $this->scrubber->fill('plg_photos_photo_translation', 'title', 'description');
        $this->scrubber->rewrite(
            'activity',
            ['meta' => "JSON_SET(meta, '$.photo_title', CONCAT('Photo ', JSON_VALUE(meta, '$.photo_id')))"],
            'type IN (:added, :commented)',
            ['added' => PhotoAdded::TYPE, 'commented' => CommentedOnPhoto::TYPE],
        );

        $context->report(sprintf('texts: %d', $texts));
    }
}
