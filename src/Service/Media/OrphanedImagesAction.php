<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;
use Psr\Clock\ClockInterface;

readonly class OrphanedImagesAction implements ActionInterface
{
    public const string IDENTIFIER = 'orphaned-images';
    public const int GRACE_DAYS = 7;
    public const int DELETE_PER_RUN = 200;
    private const int LISTED_IDS = 20;

    public function __construct(
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
        private ImageRepository $images,
        private ImageTypeRegistry $imageTypes,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::Diagnostics;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_system_orphaned_images.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_system_orphaned_images.description';
    }

    #[Override]
    public function getParameters(): array
    {
        return [ActionParameter::flag('delete', 'admin_system_orphaned_images.param_delete')];
    }

    #[Override]
    public function getFacts(): array
    {
        return [
            'admin_system_orphaned_images.fact_grace_days' => (string) self::GRACE_DAYS,
            'admin_system_orphaned_images.fact_delete_per_run' => (string) self::DELETE_PER_RUN,
        ];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $delete = (bool) ($values['delete'] ?? false);
        $complete = $this->imageLocationService->discover();
        if ($delete && !$complete) {
            return new Result(
                Status::error,
                'Image location discovery failed for at least one image type, so nothing was deleted. The error log names the definition.',
            );
        }

        $cutoff = $this->clock->now()->modify(sprintf('-%d days', self::GRACE_DAYS));
        $types = array_map(static fn(ImageTypeDefinitionInterface $definition): ImageType => $definition->getType(), $this->imageTypes->all());
        $total = $this->images->countUnlocatedBefore($cutoff, $types);
        if ($total === 0) {
            return new Result(Status::ok, sprintf('No image older than %d days is unused.', self::GRACE_DAYS));
        }

        if (!$delete) {
            $ids = array_map(static fn(Image $image): string => '#' . $image->getId(), $this->images->findUnlocatedBefore($cutoff, $types, self::LISTED_IDS));

            return new Result(Status::warning, sprintf(
                '%d image(s) older than %d days are used nowhere: %s%s. Run again with delete to remove them.',
                $total,
                self::GRACE_DAYS,
                implode(', ', $ids),
                $total > count($ids) ? ', ...' : '',
            ));
        }

        $deleted = 0;
        foreach ($this->images->findUnlocatedBefore($cutoff, $types, self::DELETE_PER_RUN) as $image) {
            if ($this->imageService->removeIfOrphaned($image)) {
                ++$deleted;
            }
        }

        $remaining = $total - $deleted;
        if ($remaining > 0) {
            return new Result(Status::warning, sprintf('Deleted %d unused image(s); %d remain, run again to continue.', $deleted, $remaining));
        }

        return new Result(Status::ok, sprintf('Deleted %d unused image(s).', $deleted));
    }
}
