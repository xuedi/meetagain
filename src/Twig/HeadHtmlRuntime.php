<?php declare(strict_types=1);

namespace App\Twig;

use App\Publisher\HeadHtml\Registry;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class HeadHtmlRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Registry $registry,
    ) {}

    public function render(): string
    {
        return $this->registry->render();
    }
}
