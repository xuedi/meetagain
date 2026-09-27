<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class OutboundMailerNotBlocklistedRule implements GuardRuleInterface
{
    public function __construct(
        private BlocklistInterface $blocklist,
        private ConfigService $config,
    ) {}

    public function getName(): string
    {
        return 'outbound_mailer_not_blocklisted';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Database;
    }

    public function evaluate(array $context): GuardResult
    {
        if ($this->blocklist->isBlocked($this->config->getMailerAddress()->getAddress())) {
            return GuardResult::skip($this->getName(), 'Outbound mailer address is on the global email blocklist.');
        }

        return GuardResult::pass($this->getName());
    }
}
