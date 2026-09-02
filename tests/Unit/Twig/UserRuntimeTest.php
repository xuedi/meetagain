<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Member\UserService;
use App\Twig\UserRuntime;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserRuntimeTest extends TestCase
{
    private Stub&UserService $userServiceStub;
    private UserRuntime $subject;

    protected function setUp(): void
    {
        $this->userServiceStub = $this->createStub(UserService::class);
        $this->subject = new UserRuntime($this->userServiceStub, [], []);
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
