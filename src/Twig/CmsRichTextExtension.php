<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Cms\RichTextNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class CmsRichTextExtension extends AbstractExtension
{
    public function __construct(
        private readonly RichTextNormalizer $normalizer,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('cms_editor_html', $this->editorHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function editorHtml(?string $content): string
    {
        return $this->normalizer->toEditor((string) $content);
    }
}
