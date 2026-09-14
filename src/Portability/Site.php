<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;

readonly class Site
{
    /**
     * @param list<string> $languages
     * @param array<string, string> $themeColors
     * @param array<string, bool> $settings
     * @param array<string, array<string, mixed>> $pluginSettings
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public array $languages = [],
        public array $themeColors = [],
        public ?Image $logo = null,
        public array $settings = [],
        public array $pluginSettings = [],
    ) {}
}
