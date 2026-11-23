<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use App\Entity\User;
use App\Enum\UserRole;
use Override;

readonly class UsersAnonymizer implements AnonymizerInterface
{
    public function __construct(
        private Scrubber $scrubber,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns(
                'user',
                rewritten: ['name', 'email', 'password', 'bio'],
                cleared: ['city_id', 'regcode', 'regcode_expires_at'],
                kept: ['locale', 'notification_settings'],
            ),
            Claim::columns('user_block'),
            Claim::columns('rsvp_guest'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        $accounts = $this->scrubber->rewrite(
            'user',
            [
                'name' => "CONCAT('member-', id)",
                'email' => $this->scrubber->address('user'),
                'password' => ':password',
                'city_id' => 'NULL',
                'regcode' => 'NULL',
                'regcode_expires_at' => 'NULL',
            ],
            'role <> :system AND email NOT LIKE :erased',
            [
                'password' => $context->passwordHash,
                'system' => UserRole::System->value,
                'erased' => '%@' . User::ERASED_EMAIL_DOMAIN,
            ],
        );
        $this->scrubber->fill('user', 'bio');

        $context->report(sprintf('accounts: %d', $accounts));
    }
}
