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
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserServiceTest extends TestCase
{
    private UserRepository|MockObject $userRepo;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $this->service = new UserService(
            userRepo: $this->userRepo,
            em: $this->createStub(EntityManagerInterface::class),
            dispatcher: $this->createStub(EntityActionDispatcher::class),
            activityService: $this->createStub(ActivityService::class),
            welcomeEmail: $this->createStub(WelcomeEmail::class),
            translator: $translator,
            clock: new MockClock(),
            deletionBlockers: [],
        );
    }

    public function testResolveUserName(): void
    {
        $this->userRepo->expects($this->once())->method('resolveUserName')->with(123)->willReturn('John Doe');

        static::assertSame('John Doe', $this->service->resolveUserName(123));
    }

    public function testResolveUserNameOfADeletedMemberIsThePlaceholder(): void
    {
        $this->userRepo->expects($this->once())->method('resolveUserName')->with(7)->willReturn(null);

        static::assertSame('shared.deleted_member', $this->service->resolveUserName(7));
    }
}
