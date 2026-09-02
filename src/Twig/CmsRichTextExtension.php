<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class CmsRichTextExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('cms_editor_html', [CmsRichTextRuntime::class, 'editorHtml'], ['is_safe' => ['html']]),
        ];
    }
}
