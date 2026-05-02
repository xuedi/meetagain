<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Guard\Rule;

use App\Emails\EmailGuardOutcome;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Service\Email\BlocklistCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

#[AllowMockObjectsWithoutExpectations]
final class RecipientNotBlocklistedRuleTest extends TestCase
{
    public function testSkipsWhenBlocklisted(): void
    {
        // Arrange
        $checker = $this->createStub(BlocklistCheckerInterface::class);
        $checker->method('isBlocked')->willReturn(true);
        $rule = new RecipientNotBlocklistedRule($checker);
        $user = new UserStub()->setEmail('blocked@example.org');

        // Act
        $result = $rule->evaluate(['user' => $user]);

        // Assert
        $this->assertSame(EmailGuardOutcome::Skip, $result->outcome);
    }

    public function testPassesWhenAllowed(): void
    {
        $checker = $this->createStub(BlocklistCheckerInterface::class);
        $checker->method('isBlocked')->willReturn(false);
        $rule = new RecipientNotBlocklistedRule($checker);
        $user = new UserStub()->setEmail('ok@example.org');

        $result = $rule->evaluate(['user' => $user]);

        $this->assertSame(EmailGuardOutcome::Pass, $result->outcome);
    }

    public function testRecipientKeyOverride(): void
    {
        $checker = $this->createStub(BlocklistCheckerInterface::class);
        $checker->method('isBlocked')->willReturn(true);
        $rule = new RecipientNotBlocklistedRule($checker, 'recipient');
        $user = new UserStub()->setEmail('blocked@example.org');

        $result = $rule->evaluate(['recipient' => $user]);

        $this->assertSame(EmailGuardOutcome::Skip, $result->outcome);
    }
}
