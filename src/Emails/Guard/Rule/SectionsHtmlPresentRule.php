<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class SectionsHtmlPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'sections_html_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('sectionsHtml', $context) || $context['sectionsHtml'] === null) {
            return GuardResult::error($this->getName(), "Context is missing 'sectionsHtml'.", 'sectionsHtml');
        }

        return GuardResult::pass($this->getName());
    }
}
