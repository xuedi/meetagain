<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Filter\Attribution\ImageAttributionFilterService;
use App\Repository\ImageRepository;

class ImageAttributionService
{
    /** @var array<int>|null */
    private ?array $visibleIds = null;
    private bool $visibleIdsResolved = false;

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly ImageAttributionFilterService $filterService,
    ) {}

    /**
     * @return array<Image>
     */
    public function getVisibleAttributedImages(): array
    {
        return $this->imageRepository->findAttributed($this->resolveVisibleIds());
    }

    public function hasAny(): bool
    {
        return $this->imageRepository->hasAttributed($this->resolveVisibleIds());
    }

    /**
     * @return array<int>|null
     */
    private function resolveVisibleIds(): ?array
    {
        if (!$this->visibleIdsResolved) {
            $this->visibleIds = $this->filterService->getVisibleImageIdFilter();
            $this->visibleIdsResolved = true;
        }

        return $this->visibleIds;
    }
}
