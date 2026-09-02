<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsEventTeaserExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_upcoming_events', [CmsEventTeaserRuntime::class, 'getUpcomingEvents']),
        ];
    }
}
