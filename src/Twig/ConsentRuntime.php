<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Member\ConsentService;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ConsentRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ConsentService $consentService,
    ) {}

    public function showOsm(): bool
    {
        return $this->consentService->getShowOsm();
    }
}
