<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Service\Notification\User\CoreNotificationProvider;
use App\Service\Notification\User\Level;
use App\Service\Support\VisibilityResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\IdentityTranslator;

class CoreNotificationProviderTest extends TestCase
{
    private function createProvider(int $involved = 0, int $elsewhere = 0, bool $isSteward = true): CoreNotificationProvider
    {
        $visibilityStub = $this->createStub(VisibilityResolver::class);
        $visibilityStub->method('countWaiting')->willReturn(['involved' => $involved, 'elsewhere' => $elsewhere]);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('isGranted')->willReturn($isSteward);

        return new CoreNotificationProvider(visibilityResolver: $visibilityStub, security: $securityStub, translator: new IdentityTranslator());
    }

    /**
     * @param list<array{string, Level, ?string}> $expected
     */
    #[DataProvider('getNotificationsProvider')]
    public function testGetNotifications(bool $isSteward, int $involved, int $elsewhere, array $expected): void
    {
        // Arrange
        $provider = $this->createProvider(involved: $involved, elsewhere: $elsewhere, isSteward: $isSteward);

        // Act
        $result = $provider->getNotifications($this->createStub(User::class));

        // Assert
        static::assertCount(count($expected), $result);
        foreach ($expected as $i => [$fragment, $level, $key]) {
            static::assertStringContainsString($fragment, $result[$i]->label);
            static::assertSame($level, $result[$i]->level);
            static::assertSame($key, $result[$i]->key());
        }
    }

    public static function getNotificationsProvider(): iterable
    {
        $action = ['chrome.notification_new_support_requests', Level::Action, 'app_admin_support_list'];
        $info = ['chrome.notification_support_requests_elsewhere', Level::Info, 'support_requests_elsewhere'];

        yield 'user below steward gets nothing' => [false, 1, 1, []];
        yield 'nothing pending gives nothing' => [true, 0, 0, []];
        yield 'involved requests give one action item' => [true, 2, 0, [$action]];
        yield 'requests elsewhere give one info item' => [true, 0, 3, [$info]];
        yield 'both give two items with distinct keys' => [true, 1, 3, [$action, $info]];
    }
}
