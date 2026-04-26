<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Emails\Types\WelcomeEmail;
use App\EntityActionDispatcher;
use App\Repository\UserRepository;
use App\Service\Member\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository|MockObject $userRepo;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->service = new UserService(
            userRepo: $this->userRepo,
            em: $this->createStub(EntityManagerInterface::class),
            dispatcher: $this->createStub(EntityActionDispatcher::class),
            activityService: $this->createStub(ActivityService::class),
            welcomeEmail: $this->createStub(WelcomeEmail::class),
        );
    }

    public function testResolveUserName(): void
    {
        $this->userRepo
            ->expects($this->once())
            ->method('resolveUserName')
            ->with(123)
            ->willReturn('John Doe');

        static::assertSame('John Doe', $this->service->resolveUserName(123));
    }
}
