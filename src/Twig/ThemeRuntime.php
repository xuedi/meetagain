<?php declare(strict_types=1);

namespace App\Twig;

use App\Theme\Colors;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ThemeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Colors $colors,
    ) {}

    public function style(): string
    {
        return $this->colors->inlineStyle();
    }
}
