<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Repository\MessageRepository;
use App\Security\UserChecker;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class UserCheckerTest extends TestCase
{

    private MockObject|ActivityService $activityService;
    private MockObject|EntityManagerInterface $em;
    private MockObject|RequestStack $requestStack;
    private MockObject|MessageRepository $msgRepo;
    private UserChecker $subject;

    protected function setUp(): void
    {
        $this->activityService = $this->createMock(ActivityService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->msgRepo = $this->createMock(MessageRepository::class);

        $this->subject = new UserChecker(
            $this->activityService,
            $this->em,
            $this->requestStack,
            $this->msgRepo
        );
    }
}
