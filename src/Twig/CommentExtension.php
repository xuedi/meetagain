<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CommentExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('comment_section', [CommentRuntime::class, 'section'], ['is_safe' => ['html']]),
        ];
    }
}
