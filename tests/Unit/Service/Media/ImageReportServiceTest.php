<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\ImageReport;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use App\Service\Media\ImageReportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ImageReportServiceTest extends TestCase
{
    public function testResolvingAReportMarksItResolvedAndSavesIt(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new ImageReportService($this->createStub(ImageReportRepository::class), $em);
        $report = new ImageReport();

        // Act
        $service->resolve($report);

        // Assert
        static::assertSame(ImageReportStatus::Resolved, $report->getStatus());
    }
}
