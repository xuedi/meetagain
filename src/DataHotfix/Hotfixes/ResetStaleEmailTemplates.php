<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Enum\EmailType;
use Module\Email\Contract\TemplatesInterface;
use Override;

readonly class ResetStaleEmailTemplates implements DataHotfixInterface
{
    private const array STALE = [
        EmailType::SupportNotification,
        EmailType::VerificationRequest,
        EmailType::Welcome,
        EmailType::PasswordResetRequest,
        EmailType::NotificationMessage,
        EmailType::NotificationRsvpAggregated,
        EmailType::NotificationEventCanceled,
        EmailType::Announcement,
    ];

    public function __construct(
        private TemplatesInterface $templates,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_10_12_reset_stale_email_templates';
    }

    #[Override]
    public function execute(): void
    {
        foreach (self::STALE as $type) {
            $this->templates->resetToDefault($type->value);
        }
    }
}
