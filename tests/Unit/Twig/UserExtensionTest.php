<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Member\UserService;
use App\Twig\UserExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserExtensionTest extends TestCase
{
    private Stub&UserService $userServiceStub;
    private UserExtension $subject;

    protected function setUp(): void
    {
        $this->userServiceStub = $this->createStub(UserService::class);
        $this->subject = new UserExtension($this->userServiceStub, [], []);
    }

    public function testGetFunctionsReturnsRegisteredTwigFunctions(): void
    {
        $functions = $this->subject->getFunctions();

        $names = array_map(static fn($f): string => $f->getName(), $functions);
        static::assertContains('get_user_name', $names);
        static::assertContains('get_member_view_actions', $names);
        static::assertContains('get_member_view_sections', $names);
    }

    public function testGetUserNameDelegatesToUserService(): void
    {
        $this->userServiceStub->method('resolveUserName')->willReturn('John Doe');

        $result = $this->subject->getUserName(42);

        static::assertSame('John Doe', $result);
    }

    public function testGetUserNameHandlesUnknownUser(): void
    {
        $this->userServiceStub->method('resolveUserName')->willReturn('[deleted]');

        $result = $this->subject->getUserName(999);

        static::assertSame('[deleted]', $result);
    }
}
