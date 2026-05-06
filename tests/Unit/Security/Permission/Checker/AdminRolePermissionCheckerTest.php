<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Permission\Checker;

use App\Entity\User;
use App\Security\Permission\Attribute\PermissionAttribute as Attr;
use App\Security\Permission\Checker\AdminRolePermissionChecker;
use App\Security\Permission\PermissionContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[AllowMockObjectsWithoutExpectations]
class AdminRolePermissionCheckerTest extends TestCase
{
    private Security $security;
    private AdminRolePermissionChecker $checker;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->checker = new AdminRolePermissionChecker($this->security);
    }

    public function testSupportsOrganizerAttribute(): void
    {
        self::assertTrue($this->checker->supports(Attr::CMS_PAGE_UPDATE, null));
        self::assertTrue($this->checker->supports(Attr::EVENT_CREATE, null));
        self::assertTrue($this->checker->supports(Attr::HOST_DELETE, null));
    }

    public function testSupportsAdminAttribute(): void
    {
        self::assertTrue($this->checker->supports(Attr::SYSTEM_LOGS_CRON_READ, null));
        self::assertTrue($this->checker->supports(Attr::EMAIL_TEMPLATE_UPDATE, null));
        self::assertTrue($this->checker->supports(Attr::MEMBER_DELETE, null));
    }

    public function testRejectsUnknownAttribute(): void
    {
        self::assertFalse($this->checker->supports('unknown.foo', null));
        self::assertFalse($this->checker->supports(Attr::EVENT_RSVP, null));
    }

    public function testAdminAlwaysAllowed(): void
    {
        $ctx = new PermissionContext(actor: new User(), subject: null, isAdmin: true);
        self::assertTrue($this->checker->vote(Attr::CMS_PAGE_DELETE, $ctx));
        self::assertTrue($this->checker->vote(Attr::SYSTEM_SETTINGS_UPDATE, $ctx));
    }

    public function testAdminOnlyAttributeDeniesOrganizer(): void
    {
        $this->security->method('isGranted')->willReturn(true);
        $ctx = new PermissionContext(actor: new User(), subject: null, isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::SYSTEM_LOGS_CRON_READ, $ctx));
        self::assertFalse($this->checker->vote(Attr::MEMBER_DELETE, $ctx));
    }

    public function testOrganizerCanPerformOrganizerAttributes(): void
    {
        $this->security->method('isGranted')->willReturn(true);
        $ctx = new PermissionContext(actor: new User(), subject: null, isAdmin: false);
        self::assertTrue($this->checker->vote(Attr::CMS_PAGE_UPDATE, $ctx));
        self::assertTrue($this->checker->vote(Attr::EVENT_UPDATE, $ctx));
    }

    public function testNonOrganizerCannotPerformOrganizerAttributes(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $ctx = new PermissionContext(actor: new User(), subject: null, isAdmin: false);
        self::assertFalse($this->checker->vote(Attr::CMS_PAGE_UPDATE, $ctx));
        self::assertFalse($this->checker->vote(Attr::EVENT_DELETE, $ctx));
    }
}
