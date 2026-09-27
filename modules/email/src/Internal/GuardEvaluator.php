<?php declare(strict_types=1);

namespace Module\Email\Internal;

use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;
use Module\Email\Contract\GuardRuleProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class GuardEvaluator
{
    /**
     * @param iterable<GuardRuleProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(GuardRuleProviderInterface::class)]
        private iterable $providers = [],
    ) {}

    public function evaluate(EmailInterface $email, array $context): GuardResult
    {
        foreach ($this->resolveChain($email) as $rule) {
            $result = $rule->evaluate($context);
            if ($result->outcome !== GuardOutcome::Pass) {
                return $result;
            }
        }

        return GuardResult::pass('chain.all');
    }

    /**
     * @return list<GuardResult>
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
     * @return list<GuardRuleInterface>
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
