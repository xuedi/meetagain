<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Permission\Checker;

use App\Entity\DeveloperAppApplication;
use App\Entity\User;
use App\Security\Permission\Attribute\PermissionAttribute as Attr;
use App\Security\Permission\Checker\UserSelfPermissionChecker;
use App\Security\Permission\PermissionContext;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class UserSelfPermissionCheckerTest extends TestCase
{
    private UserSelfPermissionChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserSelfPermissionChecker();
    }

    public function testSupportsSelfAttributes(): void
    {
        self::assertTrue($this->checker->supports(Attr::USER_UPDATE_SELF, null));
        self::assertTrue($this->checker->supports(Attr::USER_PASSWORD_UPDATE, $this->makeUser(1)));
    }

    public function testSupportsElevatedAttributes(): void
    {
        self::assertTrue($this->checker->supports(Attr::USER_VIEW, null));
        self::assertTrue($this->checker->supports(Attr::USER_UPDATE, $this->makeUser(1)));
    }

    public function testRejectsNonUserSubject(): void
    {
        self::assertFalse($this->checker->supports(Attr::USER_UPDATE_SELF, new \stdClass()));
    }

    public function testAdminAlwaysAllowed(): void
    {
        $ctx = new PermissionContext(actor: null, subject: $this->makeUser(1), isAdmin: true);
        self::assertTrue($this->checker->vote(Attr::USER_UPDATE_SELF, $ctx));
        self::assertTrue($this->checker->vote(Attr::USER_UPDATE, $ctx));
    }

    public function testElevatedAttributeDeniedForNonAdmin(): void
    {
        $actor = $this->makeUser(1);
        $ctx = new PermissionContext(actor: $actor, subject: $actor, isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::USER_UPDATE, $ctx));
        self::assertFalse($this->checker->vote(Attr::USER_VIEW, $ctx));
    }

    public function testSelfAttributeAllowsSameUser(): void
    {
        $actor = $this->makeUser(1);
        $ctx = new PermissionContext(actor: $actor, subject: $actor, isAdmin: false);
        self::assertTrue($this->checker->vote(Attr::USER_UPDATE_SELF, $ctx));
        self::assertTrue($this->checker->vote(Attr::USER_PASSWORD_UPDATE, $ctx));
    }

    public function testSelfAttributeDeniesOtherUser(): void
    {
        $ctx = new PermissionContext(actor: $this->makeUser(1), subject: $this->makeUser(2), isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::USER_UPDATE_SELF, $ctx));
        self::assertFalse($this->checker->vote(Attr::USER_PASSWORD_UPDATE, $ctx));
    }

    public function testSelfAttributeWithNullSubjectAllowsActor(): void
    {
        $ctx = new PermissionContext(actor: $this->makeUser(1), subject: null, isAdmin: false);
        self::assertTrue($this->checker->vote(Attr::USER_VIEW_SELF, $ctx));
    }

    public function testAnonymousActorDenied(): void
    {
        $ctx = new PermissionContext(actor: null, subject: $this->makeUser(1), isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::USER_UPDATE_SELF, $ctx));
    }

    public function testSupportsDeveloperAppSelfAttributes(): void
    {
        $owner = $this->makeUser(1);
        $app = new DeveloperAppApplication($owner, 'Bot', ['https://example.com/cb'], ['authorization_code']);

        self::assertTrue($this->checker->supports(Attr::DEVELOPER_APP_VIEW_SELF, $app));
        self::assertTrue($this->checker->supports(Attr::DEVELOPER_APP_MANAGE_SELF, $app));
        self::assertTrue($this->checker->supports(Attr::DEVELOPER_APP_VIEW_SELF, null));
    }

    public function testDeveloperAppSelfAttributeAllowsOwner(): void
    {
        $owner = $this->makeUser(1);
        $app = new DeveloperAppApplication($owner, 'Bot', ['https://example.com/cb'], ['authorization_code']);

        $ctx = new PermissionContext(actor: $owner, subject: $app, isAdmin: false);
        self::assertTrue($this->checker->vote(Attr::DEVELOPER_APP_VIEW_SELF, $ctx));
        self::assertTrue($this->checker->vote(Attr::DEVELOPER_APP_MANAGE_SELF, $ctx));
    }

    public function testDeveloperAppSelfAttributeDeniesOtherUser(): void
    {
        $owner = $this->makeUser(1);
        $other = $this->makeUser(2);
        $app = new DeveloperAppApplication($owner, 'Bot', ['https://example.com/cb'], ['authorization_code']);

        $ctx = new PermissionContext(actor: $other, subject: $app, isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::DEVELOPER_APP_VIEW_SELF, $ctx));
        self::assertFalse($this->checker->vote(Attr::DEVELOPER_APP_MANAGE_SELF, $ctx));
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
