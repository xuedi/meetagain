<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use App\Service\Media\ThumbnailRebuildAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\ActionQueueInterface;
use Override;

readonly class ImagesAnonymizer implements AnonymizerInterface
{
    private const int BATCH = 100;

    public function __construct(
        private ImageRepository $imageRepo,
        private ImageService $imageService,
        private EntityManagerInterface $em,
        private ActionQueueInterface $actionQueue,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 90;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('image', rewritten: ['mime_type', 'extension', 'hash'], kept: ['alt', 'alt_translations', 'attribution']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $count = 0;
        $afterId = null;
        do {
            $images = $this->imageRepo->findAfterId($afterId, self::BATCH);
            if ($images === []) {
                break;
            }
            $count += $this->imageService->replaceWithPlaceholders($images);
            $afterId = (int) $images[array_key_last($images)]->getId();
            $this->em->clear();
            $context->report(sprintf('images replaced: %d', $count));
        } while (count($images) === self::BATCH);

        $this->actionQueue->queue(ThumbnailRebuildAction::IDENTIFIER, ['type' => ThumbnailRebuildAction::ALL], null);
        $context->report('thumbnail rebuild queued for the worker');
    }
}
