<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UserExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_user_name', [UserRuntime::class, 'getUserName']),
            new TwigFunction('get_member_view_actions', [UserRuntime::class, 'getMemberViewActions'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('get_member_view_sections', [UserRuntime::class, 'getMemberViewSections'], [
                'is_safe' => ['html'],
            ]),
        ];
    }
}
