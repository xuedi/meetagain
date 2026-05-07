<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DeveloperAppApplication;
use App\Entity\User;
use App\Enum\DeveloperAppStatus;
use PHPUnit\Framework\TestCase;

final class DeveloperAppApplicationTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $user = new User();
        $app = new DeveloperAppApplication($user, 'Bot', ['https://example.com/cb'], ['authorization_code']);

        self::assertSame($user, $app->getSubmittedBy());
        self::assertSame('Bot', $app->getAppName());
        self::assertSame(['https://example.com/cb'], $app->getRedirectUris());
        self::assertSame(['authorization_code'], $app->getRequestedGrants());
        self::assertSame(['api'], $app->getRequestedScopes());
        self::assertSame(DeveloperAppStatus::Pending, $app->getStatus());
        self::assertFalse($app->isUserReadOutcome());
        self::assertFalse($app->hasUnreadOutcome());
    }

    public function testHasUnreadOutcomeRequiresFinalStatus(): void
    {
        $app = new DeveloperAppApplication(new User(), 'Bot', ['https://example.com/cb'], ['authorization_code']);
        self::assertFalse($app->hasUnreadOutcome());

        $app->setStatus(DeveloperAppStatus::Approved);
        self::assertTrue($app->hasUnreadOutcome());

        $app->setUserReadOutcome(true);
        self::assertFalse($app->hasUnreadOutcome());
    }

    public function testMarkOutcomeReadFlipsFlag(): void
    {
        $app = new DeveloperAppApplication(new User(), 'Bot', ['https://example.com/cb'], ['authorization_code']);
        $app->setStatus(DeveloperAppStatus::Denied);
        self::assertTrue($app->hasUnreadOutcome());

        $app->markOutcomeRead();
        self::assertTrue($app->isUserReadOutcome());
        self::assertFalse($app->hasUnreadOutcome());
    }
}
