<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Guard\Rule;

use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\GuardOutcome;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

#[AllowMockObjectsWithoutExpectations]
final class RecipientNotBlocklistedRuleTest extends TestCase
{
    public function testSkipsWhenBlocklisted(): void
    {
        // Arrange
        $checker = $this->createStub(BlocklistInterface::class);
        $checker->method('isBlocked')->willReturn(true);
        $rule = new RecipientNotBlocklistedRule($checker);
        $user = new UserStub()->setEmail('blocked@example.org');

        // Act
        $result = $rule->evaluate(['user' => $user]);

        // Assert
        $this->assertSame(GuardOutcome::Skip, $result->outcome);
    }

    public function testPassesWhenAllowed(): void
    {
        $checker = $this->createStub(BlocklistInterface::class);
        $checker->method('isBlocked')->willReturn(false);
        $rule = new RecipientNotBlocklistedRule($checker);
        $user = new UserStub()->setEmail('ok@example.org');

        $result = $rule->evaluate(['user' => $user]);

        $this->assertSame(GuardOutcome::Pass, $result->outcome);
    }

    public function testRecipientKeyOverride(): void
    {
        $checker = $this->createStub(BlocklistInterface::class);
        $checker->method('isBlocked')->willReturn(true);
        $rule = new RecipientNotBlocklistedRule($checker, 'recipient');
        $user = new UserStub()->setEmail('blocked@example.org');

        $result = $rule->evaluate(['recipient' => $user]);

        $this->assertSame(GuardOutcome::Skip, $result->outcome);
    }
}
