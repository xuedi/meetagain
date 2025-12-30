<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\UserService;
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
        $this->subject = new UserExtension($this->userServiceStub);
    }

    public function testGetFunctionsReturnsGetUserNameFunction(): void
    {
        $functions = $this->subject->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('get_user_name', $functions[0]->getName());
    }

    public function testGetUserNameDelegatesToUserService(): void
    {
        $this->userServiceStub
            ->method('resolveUserName')
            ->with(42)
            ->willReturn('John Doe');

        $result = $this->subject->getUserName(42);

        $this->assertSame('John Doe', $result);
    }

    public function testGetUserNameHandlesUnknownUser(): void
    {
        $this->userServiceStub
            ->method('resolveUserName')
            ->with(999)
            ->willReturn('[deleted]');

        $result = $this->subject->getUserName(999);

        $this->assertSame('[deleted]', $result);
    }
}
