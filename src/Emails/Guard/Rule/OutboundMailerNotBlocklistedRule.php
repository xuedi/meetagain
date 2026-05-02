<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;

final readonly class OutboundMailerNotBlocklistedRule implements EmailGuardRuleInterface
{
    public function __construct(
        private BlocklistCheckerInterface $blocklist,
        private ConfigService $config,
    ) {}

    public function getName(): string
    {
        return 'outbound_mailer_not_blocklisted';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Database;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if ($this->blocklist->isBlocked($this->config->getMailerAddress()->getAddress())) {
            return EmailGuardResult::skip(
                $this->getName(),
                'Outbound mailer address is on the global email blocklist.',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
