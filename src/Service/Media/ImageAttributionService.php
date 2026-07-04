<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Filter\Attribution\ImageAttributionFilterService;
use App\Repository\ImageRepository;

/**
 * Resolves the attributed images visible in the current context, applying the
 * attribution filter chain (which plugins use to narrow visibility).
 */
readonly class ImageAttributionService
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageAttributionFilterService $filterService,
    ) {}

    /**
     * @return array<Image>
     */
    public function getVisibleAttributedImages(): array
    {
        return $this->imageRepository->findAttributed($this->filterService->getVisibleImageIdFilter());
    }

    public function hasAny(): bool
    {
        return $this->imageRepository->hasAttributed($this->filterService->getVisibleImageIdFilter());
    }
}
