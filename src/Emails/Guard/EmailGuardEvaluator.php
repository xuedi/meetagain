<?php declare(strict_types=1);

namespace App\Emails\Guard;

use App\Emails\EmailGuardOutcome;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Emails\EmailGuardRuleProviderInterface;
use App\Emails\EmailInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EmailGuardEvaluator
{
    /**
     * @param iterable<EmailGuardRuleProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(EmailGuardRuleProviderInterface::class)]
        private iterable $providers = [],
    ) {}

    /**
     * Run the chain in order, short-circuit on first non-Pass result. Returns the offending result
     * or a synthetic Pass when all rules pass.
     */
    public function evaluate(EmailInterface $email, array $context): EmailGuardResult
    {
        foreach ($this->resolveChain($email) as $rule) {
            $result = $rule->evaluate($context);
            if ($result->outcome !== EmailGuardOutcome::Pass) {
                return $result;
            }
        }

        return EmailGuardResult::pass('chain.all');
    }

    /**
     * Run every rule regardless of outcome. Used by the admin guard-detail page so the operator
     * can see the full chain even past the short-circuit point.
     *
     * @return list<EmailGuardResult>
     */
    public function evaluateAll(EmailInterface $email, array $context): array
    {
        $results = [];
        foreach ($this->resolveChain($email) as $rule) {
            $results[] = $rule->evaluate($context);
        }

        return $results;
    }

    /**
     * @return list<EmailGuardRuleInterface>
     */
    private function resolveChain(EmailInterface $email): array
    {
        $chain = $email->getGuardRules();
        foreach ($this->providers as $provider) {
            foreach ($provider->getRulesFor($email->getIdentifier()) as $rule) {
                $chain[] = $rule;
            }
        }

        return $chain;
    }
}
