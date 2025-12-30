<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\UserRepository;
use App\Service\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository|MockObject $userRepo;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->service = new UserService($this->userRepo);
    }

    public function testResolveUserName(): void
    {
        $this->userRepo->expects($this->once())
            ->method('resolveUserName')
            ->with(123)
            ->willReturn('John Doe');

        $this->assertEquals('John Doe', $this->service->resolveUserName(123));
    }
}
