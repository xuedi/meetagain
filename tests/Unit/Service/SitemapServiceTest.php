<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Repository\EventRepository;
use App\Service\CmsService;
use App\Service\SitemapService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class SitemapServiceTest extends TestCase
{
    private MockObject|Environment $environmentMock;
    private MockObject|CmsService $cmsServiceMock;
    private MockObject|EventRepository $eventRepositoryMock;
    private MockObject|ParameterBagInterface $parameterBagInterfaceMock;
    private SitemapService $subject;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        $this->cmsServiceMock = $this->createMock(CmsService::class);
        $this->eventRepositoryMock = $this->createMock(EventRepository::class);
        $this->parameterBagInterfaceMock = $this->createMock(ParameterBagInterface::class);

        $this->subject = new SitemapService(
            twig: $this->environmentMock,
            cms: $this->cmsServiceMock,
            events: $this->eventRepositoryMock,
            appParams: $this->parameterBagInterfaceMock,
        );
    }
}
