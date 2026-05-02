<?php

declare(strict_types=1);

namespace Tests\Unit\Emails\Guard\Rule;

use App\Emails\EmailGuardOutcome;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

final class RecipientUserPresentRuleTest extends TestCase
{
    public function testPassesWhenUserPresent(): void
    {
        // Arrange
        $rule = new RecipientUserPresentRule();

        // Act
        $result = $rule->evaluate(['user' => new UserStub()]);

        // Assert
        $this->assertSame(EmailGuardOutcome::Pass, $result->outcome);
    }

    public function testErrorWhenKeyMissing(): void
    {
        $rule = new RecipientUserPresentRule();
        $result = $rule->evaluate([]);
        $this->assertSame(EmailGuardOutcome::Error, $result->outcome);
        $this->assertSame('user', $result->contextKey);
    }

    public function testErrorWhenNotUserInstance(): void
    {
        $rule = new RecipientUserPresentRule();
        $result = $rule->evaluate(['user' => 'string']);
        $this->assertSame(EmailGuardOutcome::Error, $result->outcome);
    }
}
