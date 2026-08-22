<?php declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Component\Mime\Part\DataPart;

readonly class RenderedLayout
{
    public function __construct(
        public string $html,
        public ?DataPart $inlineLogo = null,
    ) {}
}
