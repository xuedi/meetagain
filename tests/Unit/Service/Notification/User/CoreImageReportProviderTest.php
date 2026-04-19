<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\ImageReport;
use App\Entity\User;
use App\Repository\ImageReportRepository;
use App\Service\Notification\User\CoreImageReportProvider;
use App\Service\Notification\User\ReviewNotificationItem;
use Doctrine\ORM\EntityManagerInterface;
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
}
