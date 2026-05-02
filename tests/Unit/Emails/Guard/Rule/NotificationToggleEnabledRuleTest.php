<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Guard\Rule;

use App\Emails\EmailGuardOutcome;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Entity\NotificationSettings;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

final class NotificationToggleEnabledRuleTest extends TestCase
{
    public function testPassesWhenToggleOn(): void
    {
        $rule = new NotificationToggleEnabledRule('upcomingEvents');
        $user = new UserStub()->setNotificationSettings(new NotificationSettings(['upcomingEvents' => true]));

        $result = $rule->evaluate(['user' => $user]);

        $this->assertSame(EmailGuardOutcome::Pass, $result->outcome);
    }

    public function testSkipsWhenToggleOff(): void
    {
        $rule = new NotificationToggleEnabledRule('upcomingEvents');
        $user = new UserStub()->setNotificationSettings(new NotificationSettings(['upcomingEvents' => false]));

        $result = $rule->evaluate(['user' => $user]);

        $this->assertSame(EmailGuardOutcome::Skip, $result->outcome);
    }
}
