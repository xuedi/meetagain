<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Enum\ImageReportReason;
use App\Repository\ImageReportRepository;
use App\Service\Notification\Admin\ReportedImageAdminNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReportedImageAdminNotificationProviderTest extends TestCase
{
    public function testGetSectionReturnsExpectedString(): void
    {
        // Arrange
        $provider = new ReportedImageAdminNotificationProvider(
            imageReportRepository: $this->createStub(ImageReportRepository::class),
        );

        // Act & Assert
        static::assertSame('Reported Images', $provider->getSection());
    }

    public function testGetPendingItemsWithNoReportsReturnsEmptyArray(): void
    {
        // Arrange
        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act & Assert
        static::assertSame([], $provider->getPendingItems());
    }

    public function testGetPendingItemsWithOneReportImageExistsContainsIdAndReason(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn(42);

        $report = $this->createStub(ImageReport::class);
        $report->method('getImage')->willReturn($image);
        $report->method('getReason')->willReturn(ImageReportReason::Privacy);

        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([$report]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act
        $items = $provider->getPendingItems();

        // Assert
        static::assertCount(1, $items);
        static::assertStringContainsString('42', $items[0]->label);
        static::assertStringContainsString('Privacy', $items[0]->label);
    }

    public function testGetPendingItemsWithDeletedImageUsesDeletedPlaceholder(): void
    {
        // Arrange: getImage() returns null → image was deleted
        $report = $this->createStub(ImageReport::class);
        $report->method('getImage')->willReturn(null);
        $report->method('getReason')->willReturn(ImageReportReason::Inappropriate);

        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([$report]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act
        $items = $provider->getPendingItems();

        // Assert
        static::assertStringContainsString('deleted', $items[0]->label);
    }

    public function testGetLatestPendingAtWithEmptyReportsReturnsNull(): void
    {
        // Arrange
        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act & Assert
        static::assertNull($provider->getLatestPendingAt());
    }

    public function testGetLatestPendingAtWithReportsReturnsFirstReportCreatedAt(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-09-15 12:00:00');

        $report = $this->createStub(ImageReport::class);
        $report->method('getCreatedAt')->willReturn($date);
        $report->method('getImage')->willReturn(null);
        $report->method('getReason')->willReturn(ImageReportReason::Copyright);

        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([$report]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act & Assert
        static::assertSame($date, $provider->getLatestPendingAt());
    }
}
