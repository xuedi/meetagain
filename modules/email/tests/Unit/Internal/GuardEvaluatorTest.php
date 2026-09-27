<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleProviderInterface;
use Module\Email\Internal\GuardEvaluator;
use Module\Email\Tests\Stub\GuardRule;
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
        $rule1 = GuardRule::returning(GuardResult::pass('rule1'));
        $rule2 = GuardRule::returning(GuardResult::skip('rule2', 'opted out'));
        $rule3 = GuardRule::returning(GuardResult::pass('rule3'));

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new GuardEvaluator([]);

        // Act
        $result = $evaluator->evaluate($email, []);

        // Assert
        $this->assertSame(GuardOutcome::Skip, $result->outcome);
        $this->assertSame('rule2', $result->ruleName);
        $this->assertSame(1, $rule1->calls);
        $this->assertSame(1, $rule2->calls);
        $this->assertSame(0, $rule3->calls);
    }

    public function testEvaluateAllRunsEveryRule(): void
    {
        // Arrange
        $rule1 = GuardRule::returning(GuardResult::pass('rule1'));
        $rule2 = GuardRule::returning(GuardResult::skip('rule2', 'x'));
        $rule3 = GuardRule::returning(GuardResult::pass('rule3'));

        $email = $this->createStub(EmailInterface::class);
        $email->method('getGuardRules')->willReturn([$rule1, $rule2, $rule3]);
        $evaluator = new GuardEvaluator([]);

        // Act
        $results = $evaluator->evaluateAll($email, []);

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame(['rule1', 'rule2', 'rule3'], array_map(static fn(GuardResult $r) => $r->ruleName, $results));
        $this->assertSame(1, $rule3->calls);
    }

    public function testProviderRulesAreAppendedAfterCoreRules(): void
    {
        // Arrange
        $coreRule = GuardRule::returning(GuardResult::pass('core'));
        $pluginRule = GuardRule::returning(GuardResult::pass('plugin'));

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
}
