<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Entity\User;
use App\Enum\ImageReportReason;
use App\Repository\ImageReportRepository;
use App\Service\Notification\Admin\ReportedImageAdminNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ReportedImageAdminNotificationProviderTest extends TestCase
{
    public function testGetSectionReturnsExpectedString(): void
    {
        // Arrange
        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $this->createStub(ImageReportRepository::class));

        // Act
        $section = $provider->getSection();

        // Assert
        static::assertInstanceOf(TranslatableMessage::class, $section);
        static::assertSame('notifications.section_reported_images', $section->getMessage());
    }

    public function testGetPendingItemsWithNoReportsReturnsEmptyArray(): void
    {
        // Arrange
        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act & Assert
        static::assertSame([], $provider->getPendingItems($this->createStub(User::class)));
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
        $items = $provider->getPendingItems($this->createStub(User::class));

        // Assert
        static::assertCount(1, $items);
        $label = $items[0]->label;
        static::assertInstanceOf(TranslatableMessage::class, $label);
        static::assertSame('notifications.item_reported_image', $label->getMessage());
        static::assertSame('42', $label->getParameters()['%image%']);
        static::assertSame('report.reason_privacy', $label->getParameters()['%reason%']->getMessage());
    }

    public function testGetPendingItemsWithDeletedImageUsesDeletedPlaceholder(): void
    {
        // Arrange
        $report = $this->createStub(ImageReport::class);
        $report->method('getImage')->willReturn(null);
        $report->method('getReason')->willReturn(ImageReportReason::Inappropriate);

        $repoStub = $this->createStub(ImageReportRepository::class);
        $repoStub->method('getOpen')->willReturn([$report]);

        $provider = new ReportedImageAdminNotificationProvider(imageReportRepository: $repoStub);

        // Act
        $items = $provider->getPendingItems($this->createStub(User::class));

        // Assert
        $label = $items[0]->label;
        static::assertInstanceOf(TranslatableMessage::class, $label);
        static::assertSame('notifications.image_deleted', $label->getParameters()['%image%']->getMessage());
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
