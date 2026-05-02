<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;

final readonly class RenderedAnnouncementPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'rendered_announcement_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        foreach (['renderedContent', 'announcementUrl'] as $key) {
            if (!array_key_exists($key, $context) || $context[$key] === null) {
                return EmailGuardResult::error(
                    $this->getName(),
                    "Context is missing 'renderedContent' and/or 'announcementUrl'.",
                    $key,
                );
            }
        }

        return EmailGuardResult::pass($this->getName());
    }
}
