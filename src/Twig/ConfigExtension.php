<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Config\ConfigService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConfigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_show_frontpage', $this->configService->isShowFrontpage(...)),
            new TwigFunction('get_theme_colors', $this->configService->getThemeColors(...)),
            new TwigFunction('get_date_format', $this->configService->getDateFormat(...)),
            new TwigFunction('get_date_format_flatpickr', $this->configService->getDateFormatFlatpickr(...)),
            new TwigFunction('get_footer_column_title', $this->configService->getFooterColumnTitle(...)),
        ];
    }
}
