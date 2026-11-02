<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Enum\ImageType;
use App\Repository\ImageRepository;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;

readonly class ThumbnailRebuildAction implements ActionInterface
{
    public const string IDENTIFIER = 'thumbnail-rebuild';
    public const string ALL = '*';
    public const int IMAGES_PER_STEP = 100;

    public function __construct(
        private ImageService $imageService,
        private ImageRepository $images,
        private ImageTypeRegistry $imageTypes,
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
        return 'admin_system_thumbnails.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_system_thumbnails.description';
    }

    #[Override]
    public function getParameters(): array
    {
        $choices = ['admin_system_thumbnails.choice_all' => self::ALL];
        foreach ($this->imageTypes->all() as $definition) {
            $choices[$definition->getType()->name] = $definition->getType()->name;
        }

        return [ActionParameter::choice('type', 'admin_system_thumbnails.param_type', $choices, self::ALL)];
    }

    #[Override]
    public function getFacts(): array
    {
        return ['admin_system_thumbnails.fact_images' => (string) $this->images->count()];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $name = $values['type'] ?? null;
        $only = null;
        if (is_string($name) && $name !== self::ALL) {
            $only = array_find(ImageType::cases(), static fn(ImageType $type): bool => $type->name === $name);
            if ($only === null) {
                return new Result(Status::error, sprintf('Unknown image type "%s".', $name));
            }
        }

        [$afterId, $createdSoFar] = array_pad(array_map(intval(...), explode(':', $cursor ?? '')), 2, 0);
        $batch = $this->imageService->regenerateThumbnailsAfter($afterId === 0 ? null : $afterId, $this->imagesPerStep, $only);
        $created = $createdSoFar + $batch['created'];
        $scope = $only === null ? 'across all image types' : 'for image type ' . $only->name;

        if ($batch['lastImageId'] !== null) {
            return new Result(
                Status::ok,
                sprintf('Created %d missing thumbnail(s) %s, up to image #%d', $created, $scope, $batch['lastImageId']),
                continueFrom: $batch['lastImageId'] . ':' . $created,
            );
        }

        return new Result(Status::ok, sprintf('Created %d missing thumbnail(s) %s.', $created, $scope));
    }
}
