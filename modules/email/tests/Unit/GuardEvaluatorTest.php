<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;
use Module\Email\Contract\GuardRuleProviderInterface;
use Module\Email\Internal\GuardEvaluator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GuardEvaluatorTest extends TestCase
{
    public function testReturnsSyntheticPassWhenChainEmpty(): void
    {
        // Arrange
        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([]);
        $evaluator = new GuardEvaluator([]);

        // Act
        $result = $evaluator->evaluate($email, []);

        // Assert
        $this->assertSame(GuardOutcome::Pass, $result->outcome);
    }

    public function testShortCircuitsOnFirstNonPass(): void
    {
        // Arrange
        $invocations = ['rule1' => 0, 'rule2' => 0, 'rule3' => 0];
        $rule1 = $this->makeRule('rule1', GuardResult::pass('rule1'), $invocations);
        $rule2 = $this->makeRule('rule2', GuardResult::skip('rule2', 'opted out'), $invocations);
        $rule3 = $this->makeRule('rule3', GuardResult::pass('rule3'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new GuardEvaluator([]);

        // Act
        $result = $evaluator->evaluate($email, []);

        // Assert
        $this->assertSame(GuardOutcome::Skip, $result->outcome);
        $this->assertSame('rule2', $result->ruleName);
        $this->assertSame(1, $invocations['rule1']);
        $this->assertSame(1, $invocations['rule2']);
        $this->assertSame(0, $invocations['rule3'], 'rule3 must not run after short-circuit');
    }

    public function testEvaluateAllRunsEveryRule(): void
    {
        // Arrange
        $invocations = ['rule1' => 0, 'rule2' => 0, 'rule3' => 0];
        $rule1 = $this->makeRule('rule1', GuardResult::pass('rule1'), $invocations);
        $rule2 = $this->makeRule('rule2', GuardResult::skip('rule2', 'x'), $invocations);
        $rule3 = $this->makeRule('rule3', GuardResult::pass('rule3'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new GuardEvaluator([]);

        // Act
        $results = $evaluator->evaluateAll($email, []);

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame(['rule1', 'rule2', 'rule3'], array_map(static fn(GuardResult $r) => $r->ruleName, $results));
        $this->assertSame(1, $invocations['rule3'], 'rule3 runs even after a Skip');
    }

    public function testProviderRulesAreAppendedAfterCoreRules(): void
    {
        // Arrange
        $invocations = ['core' => 0, 'plugin' => 0];
        $coreRule = $this->makeRule('core', GuardResult::pass('core'), $invocations);
        $pluginRule = $this->makeRule('plugin', GuardResult::pass('plugin'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getGuardRules')->willReturn([$coreRule]);

        $provider = $this->createStub(GuardRuleProviderInterface::class);
        $provider->method('getRulesFor')->willReturnCallback(static fn(string $id): array => $id === 'test.email' ? [$pluginRule] : []);

        $evaluator = new GuardEvaluator([$provider]);

        // Act
        $results = $evaluator->evaluateAll($email, []);

        // Assert
        $this->assertCount(2, $results);
        $this->assertSame('core', $results[0]->ruleName);
        $this->assertSame('plugin', $results[1]->ruleName);
    }

    private function makeRule(string $name, GuardResult $result, array &$invocations): GuardRuleInterface
    {
        return new class($name, $result, $invocations) implements GuardRuleInterface {
            public function __construct(
                private readonly string $ruleName,
                private readonly GuardResult $result,
                private array &$invocations,
            ) {}

            public function getName(): string
            {
                return $this->ruleName;
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                $this->invocations[$this->ruleName]++;

                return $this->result;
            }
        };
    }
}
