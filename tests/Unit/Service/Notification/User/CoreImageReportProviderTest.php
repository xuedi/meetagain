<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\ImageReport;
use App\Entity\User;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use App\Service\Notification\User\CoreImageReportProvider;
use App\Service\Notification\User\ReviewNotificationItem;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CoreImageReportProviderTest extends TestCase
{
    private function makeReport(int $id = 1, ?string $reporterName = 'Jane'): ImageReport
    {
        $reporter = null;
        if ($reporterName !== null) {
            $reporter = $this->createStub(User::class);
            $reporter->method('getName')->willReturn($reporterName);
        }

        $report = $this->createStub(ImageReport::class);
        $report->method('getId')->willReturn($id);
        $report->method('getReporter')->willReturn($reporter);

        return $report;
    }

    private function makeProvider(array $openReports = [], bool $isAdmin = true): CoreImageReportProvider
    {
        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('getOpen')->willReturn($openReports);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        return new CoreImageReportProvider(
            imageReportRepo: $repo,
            em: $this->createStub(EntityManagerInterface::class),
            security: $security,
        );
    }

    public function testGetReviewItemsReturnsOneItemPerOpenReport(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $provider = $this->makeProvider(openReports: [$this->makeReport(1), $this->makeReport(2)]);

        // Act
        $items = $provider->getReviewItems($admin);

        // Assert
        static::assertCount(2, $items);
        static::assertInstanceOf(ReviewNotificationItem::class, $items[0]);
        static::assertSame('1', $items[0]->id);
    }

    public function testGetReviewItemsReturnsEmptyForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(openReports: [$this->makeReport()], isAdmin: false);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertSame([], $items);
    }

    public function testApproveItemThrowsForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($user, '1');
    }

    public function testDenyItemThrowsForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($user, '1');
    }

    public function testGetIdentifierIsStable(): void
    {
        static::assertSame('core.image_report', $this->makeProvider()->getIdentifier());
    }

    public function testApproveItemThrowsWhenReportNotFound(): void
    {
        // Arrange - admin but repo finds nothing
        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('find')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreImageReportProvider(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            $security,
        );

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $provider->approveItem($this->createStub(User::class), '404');
    }

    public function testDenyItemThrowsWhenReportNotFound(): void
    {
        // Arrange
        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('find')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreImageReportProvider(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            $security,
        );

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $provider->denyItem($this->createStub(User::class), '404');
    }

    public function testApproveItemResolvesAndPersistsReport(): void
    {
        // Arrange
        $report = $this->createMock(ImageReport::class);
        $report->expects($this->once())->method('setStatus')->with(ImageReportStatus::Resolved);

        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('find')->willReturn($report);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($report);
        $em->expects($this->once())->method('flush');

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreImageReportProvider($repo, $em, $security);

        // Act
        $provider->approveItem($this->createStub(User::class), '1');
    }

    public function testDenyItemResolvesAndPersistsReport(): void
    {
        // Arrange - deny and approve share the same body in this provider
        $report = $this->createMock(ImageReport::class);
        $report->expects($this->once())->method('setStatus')->with(ImageReportStatus::Resolved);

        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('find')->willReturn($report);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($report);
        $em->expects($this->once())->method('flush');

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreImageReportProvider($repo, $em, $security);

        // Act
        $provider->denyItem($this->createStub(User::class), '1');
    }
}
