<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Config\ConfigService;
use App\Service\Media\ImageAttributionService;
use App\Service\Media\SiteLogoResolver;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ConfigRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ConfigService $configService,
        private SiteLogoResolver $siteLogoResolver,
        private ImageAttributionService $imageAttributionService,
    ) {}

    public function getDateFormat(): string
    {
        return $this->configService->getDateFormat();
    }

    public function getDateFormatFlatpickr(): string
    {
        return $this->configService->getDateFormatFlatpickr();
    }

    public function getFooterColumnTitle(string $column): string
    {
        return $this->configService->getFooterColumnTitle($column);
    }

    /**
     * @return array{url: string, width: ?int, height: ?int}
     */
    public function siteLogo(): array
    {
        return $this->siteLogoResolver->resolve();
    }

    public function hasImageAttributions(): bool
    {
        return $this->imageAttributionService->hasAny();
    }
}
