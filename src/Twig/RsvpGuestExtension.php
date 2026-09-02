<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RsvpGuestExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('rsvp_guest_count', [RsvpGuestRuntime::class, 'getGuestCount']),
        ];
    }
}
