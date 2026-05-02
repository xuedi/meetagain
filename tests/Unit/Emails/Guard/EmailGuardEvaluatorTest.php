<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Guard;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardOutcome;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Emails\EmailGuardRuleProviderInterface;
use App\Emails\EmailInterface;
use App\Emails\Guard\EmailGuardEvaluator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class EmailGuardEvaluatorTest extends TestCase
{
    public function testReturnsSyntheticPassWhenChainEmpty(): void
    {
        // Arrange
        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([]);
        $evaluator = new EmailGuardEvaluator([]);

        // Act
        $result = $evaluator->evaluate($email, []);

        // Assert
        $this->assertSame(EmailGuardOutcome::Pass, $result->outcome);
    }

    public function testShortCircuitsOnFirstNonPass(): void
    {
        // Arrange
        $invocations = ['rule1' => 0, 'rule2' => 0, 'rule3' => 0];
        $rule1 = $this->makeRule('rule1', EmailGuardResult::pass('rule1'), $invocations);
        $rule2 = $this->makeRule('rule2', EmailGuardResult::skip('rule2', 'opted out'), $invocations);
        $rule3 = $this->makeRule('rule3', EmailGuardResult::pass('rule3'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new EmailGuardEvaluator([]);

        // Act
        $result = $evaluator->evaluate($email, []);

        // Assert
        $this->assertSame(EmailGuardOutcome::Skip, $result->outcome);
        $this->assertSame('rule2', $result->ruleName);
        $this->assertSame(1, $invocations['rule1']);
        $this->assertSame(1, $invocations['rule2']);
        $this->assertSame(0, $invocations['rule3'], 'rule3 must not run after short-circuit');
    }

    public function testEvaluateAllRunsEveryRule(): void
    {
        // Arrange
        $invocations = ['rule1' => 0, 'rule2' => 0, 'rule3' => 0];
        $rule1 = $this->makeRule('rule1', EmailGuardResult::pass('rule1'), $invocations);
        $rule2 = $this->makeRule('rule2', EmailGuardResult::skip('rule2', 'x'), $invocations);
        $rule3 = $this->makeRule('rule3', EmailGuardResult::pass('rule3'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new EmailGuardEvaluator([]);

        // Act
        $results = $evaluator->evaluateAll($email, []);

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame(['rule1', 'rule2', 'rule3'], array_map(static fn(EmailGuardResult $r) => $r->ruleName, $results));
        $this->assertSame(1, $invocations['rule3'], 'rule3 runs even after a Skip');
    }

    public function testProviderRulesAreAppendedAfterCoreRules(): void
    {
        // Arrange
        $invocations = ['core' => 0, 'plugin' => 0];
        $coreRule = $this->makeRule('core', EmailGuardResult::pass('core'), $invocations);
        $pluginRule = $this->makeRule('plugin', EmailGuardResult::pass('plugin'), $invocations);

        $email = $this->createStub(EmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getGuardRules')->willReturn([$coreRule]);

        $provider = $this->createStub(EmailGuardRuleProviderInterface::class);
        $provider->method('getRulesFor')->willReturnCallback(
            static fn(string $id): array => $id === 'test.email' ? [$pluginRule] : [],
        );

        $evaluator = new EmailGuardEvaluator([$provider]);

        // Act
        $results = $evaluator->evaluateAll($email, []);

        // Assert
        $this->assertCount(2, $results);
        $this->assertSame('core', $results[0]->ruleName);
        $this->assertSame('plugin', $results[1]->ruleName);
    }

    private function makeRule(string $name, EmailGuardResult $result, array &$invocations): EmailGuardRuleInterface
    {
        return new class ($name, $result, $invocations) implements EmailGuardRuleInterface {
            public function __construct(
                private readonly string $ruleName,
                private readonly EmailGuardResult $result,
                private array &$invocations,
            ) {}
            public function getName(): string { return $this->ruleName; }
            public function getCost(): EmailGuardCost { return EmailGuardCost::Free; }
            public function evaluate(array $context): EmailGuardResult
            {
                $this->invocations[$this->ruleName]++;

                return $this->result;
            }
        };
    }
}
