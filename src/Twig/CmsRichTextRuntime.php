<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Cms\RichTextNormalizer;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CmsRichTextRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RichTextNormalizer $normalizer,
    ) {}

    public function editorHtml(?string $content): string
    {
        return $this->normalizer->toEditor((string) $content);
    }
}
