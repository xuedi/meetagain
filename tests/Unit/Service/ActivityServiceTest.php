<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Factory\ActivityMessageFactory;
use App\Repository\ActivityRepository;
use App\Service\ActivityService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActivityServiceTest extends TestCase
{
    private MockObject|EntityManagerInterface $emMock;
    private MockObject|ActivityRepository $activityRepoMock;
    private MockObject|NotificationService $notificationServiceMock;
    private MockObject|ActivityMessageFactory $messageFactoryMock;
    private ActivityService $subject;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->activityRepoMock = $this->createMock(ActivityRepository::class);
        $this->notificationServiceMock = $this->createMock(NotificationService::class);
        $this->messageFactory = $this->createMock(ActivityMessageFactory::class);
        $this->subject = new ActivityService(
            em: $this->emMock,
            repo: $this->activityRepoMock,
            notificationService: $this->notificationServiceMock,
            messageFactory: $this->messageFactory,
        );
    }

    public function testLog(): void
    {
        $this->markTestSkipped('wait until refactoring');
    }

}
