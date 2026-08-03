<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use App\Publisher\WellKnown\LlmsTxt\Renderer;
use App\Publisher\WellKnown\LlmsTxt\SectionBuilder;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\HttpFoundation\Request;

final readonly class LlmsTxtProvider implements WellKnownProviderInterface
{
    public const string SUFFIX = 'llms.txt';

    public const string CONTENT_TYPE = 'text/plain; charset=utf-8';

    public const int MAX_AGE = 3600;

    public function __construct(
        private ConfigService $configService,
        private LanguageService $languageService,
        private SectionBuilder $sectionBuilder,
        private Renderer $renderer,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return self::SUFFIX;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function provide(Request $request): ?WellKnownDocument
    {
        $locale = $this->languageService->getFilteredDefaultLocale();

        return WellKnownDocument::of(
            $this->renderer->render(
                $this->configService->getSiteName(),
                $this->configService->getSeoDescription('default'),
                $this->sectionBuilder->build($request, $locale),
            ),
            self::CONTENT_TYPE,
            self::MAX_AGE,
        );
    }
}
