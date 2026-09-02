<?php declare(strict_types=1);

namespace Plugin\Photos\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ExifExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('photo_camera_rows', [ExifRuntime::class, 'rows']),
        ];
    }
}
