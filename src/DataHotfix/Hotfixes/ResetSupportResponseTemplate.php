<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Enum\EmailType;
use Module\Email\Contract\TemplatesInterface;
use Override;

readonly class ResetSupportResponseTemplate implements DataHotfixInterface
{
    public function __construct(
        private TemplatesInterface $templates,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_11_21_reset_support_response_template';
    }

    #[Override]
    public function execute(): void
    {
        $this->templates->resetToDefault(EmailType::SupportResponse->value);
    }
}
