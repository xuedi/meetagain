<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ContentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_message', [ContentRuntime::class, 'safeMessage'], ['is_safe' => ['html']]),
        ];
    }
}
