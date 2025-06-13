<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\Image;
use App\Entity\User;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\CleanupService;
use App\Service\CmsService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class CmsServiceTest extends TestCase
{
    private MockObject|Environment $twigMock;
    private MockObject|CmsRepository $cmsRepoMock;
    private MockObject|EventRepository $eventRepoMock;
    private CmsService $subject;

    protected function setUp(): void
    {
        $this->twigMock = $this->createMock(Environment::class);
        $this->cmsRepoMock = $this->createMock(CmsRepository::class);
        $this->eventRepoMock = $this->createMock(EventRepository::class);

        $this->subject = new CmsService(
            twig: $this->twigMock,
            repo: $this->cmsRepoMock,
            eventRepo: $this->eventRepoMock
        );
    }

    public function testRemoveImageCache(): void
    {
        $this->subject->createNotFoundPage();
    }
}
