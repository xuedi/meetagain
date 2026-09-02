<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RecurrenceExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('recurrence_label', [RecurrenceRuntime::class, 'recurrenceLabel']),
        ];
    }
}
