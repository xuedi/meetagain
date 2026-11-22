<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;

readonly class MetadataStripAction implements ActionInterface
{
    public const string IDENTIFIER = 'strip-original-metadata';
    public const int IMAGES_PER_STEP = 50;

    public function __construct(
        private ImageService $imageService,
        private ImageRepository $images,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private int $imagesPerStep = self::IMAGES_PER_STEP,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::Recalculate;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_system_image_metadata.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_system_image_metadata.description';
    }

    #[Override]
    public function getParameters(): array
    {
        return [];
    }

    #[Override]
    public function getFacts(): array
    {
        return ['admin_system_image_metadata.fact_images' => (string) $this->images->count()];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        [$afterId, $stripped, $failed] = array_pad(array_map(intval(...), explode(':', $cursor ?? '')), 3, 0);
        $batch = $this->images->findAfterId($afterId === 0 ? null : $afterId, $this->imagesPerStep);

        foreach ($batch as $image) {
            try {
                if ($this->imageService->stripOriginalMetadata($image)) {
                    ++$stripped;
                }
            } catch (RuntimeException $e) {
                ++$failed;
                $this->logger->warning('Could not strip the metadata of image {hash}: {message}', [
                    'hash' => $image->getHash(),
                    'message' => $e->getMessage(),
                ]);
            }
        }
        $this->entityManager->flush();

        $status = $failed === 0 ? Status::ok : Status::warning;
        if (count($batch) === $this->imagesPerStep) {
            $lastId = (int) $batch[array_key_last($batch)]->getId();

            return new Result(
                $status,
                sprintf('Stripped the metadata of %d original(s), %d unreadable, up to image #%d', $stripped, $failed, $lastId),
                continueFrom: sprintf('%d:%d:%d', $lastId, $stripped, $failed),
            );
        }

        return new Result($status, sprintf('Stripped the metadata of %d original(s); %d could not be read or were missing.', $stripped, $failed));
    }
}
