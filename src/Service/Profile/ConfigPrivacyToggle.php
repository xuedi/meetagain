<?php declare(strict_types=1);

namespace App\Service\Profile;

final readonly class ConfigPrivacyToggle
{
    public function __construct(
        public string $labelKey,
        public bool $currentState,
        public string $toggleUrl,
        public string $csrfTokenId,
    ) {}
}
