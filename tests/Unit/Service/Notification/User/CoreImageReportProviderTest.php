<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Entity\User;
use App\Enum\ImageReportReason;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Notification\User\CoreImageReportProvider;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CoreImageReportProviderTest extends TestCase
{
    public function testAReviewItemCarriesTheImageReasonRemarksAndLocation(): void
    {
        // Arrange
        $image = new Image();
        $report = $this->report(7, 'Jane', ImageReportReason::Copyright, 'Taken from my blog', $image);
        $provider = $this->provider(openReports: [$report], location: ['label' => 'Event: Harbour walk', 'route' => null, 'params' => []]);

        // Act
        $items = $provider->getReviewItems($this->createStub(User::class));

        // Assert
        static::assertCount(1, $items);
        static::assertSame('7', $items[0]->id);
        static::assertSame('profile_review.image_report_description|Jane|report.reason_copyright', $items[0]->description);
        static::assertSame($image, $items[0]->image);
        static::assertSame('/admin/support/reports/7', $items[0]->detailUrl);
        static::assertFalse($items[0]->canDeny);
        static::assertSame('profile_review.button_keep_image', $items[0]->approveLabelKey);
        static::assertSame(
            [
                ['label' => 'profile_review.image_report_field_remarks', 'value' => 'Taken from my blog'],
                ['label' => 'profile_review.image_report_field_location', 'value' => 'Event: Harbour walk'],
            ],
            $items[0]->fields,
        );
    }

    public function testAReportWithoutRemarksReporterOrImageStillReadsWell(): void
    {
        // Arrange
        $report = $this->report(3, null, ImageReportReason::Privacy, '  ', null);
        $provider = $this->provider(openReports: [$report]);

        // Act
        $items = $provider->getReviewItems($this->createStub(User::class));

        // Assert
        static::assertSame(
            'profile_review.image_report_description|profile_review.image_report_unknown_reporter|report.reason_privacy',
            $items[0]->description,
        );
        static::assertNull($items[0]->image);
        static::assertSame([], $items[0]->fields);
    }

    public function testGetReviewItemsReturnsEmptyForNonAdmin(): void
    {
        // Arrange
        $provider = $this->provider(openReports: [$this->report(1, 'Jane', ImageReportReason::Privacy, null, null)], isAdmin: false);

        // Act
        $items = $provider->getReviewItems($this->createStub(User::class));

        // Assert
        static::assertSame([], $items);
    }

    public function testApproveItemThrowsForNonAdmin(): void
    {
        // Arrange
        $provider = $this->provider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($this->createStub(User::class), '1');
    }

    public function testDenyItemThrowsForNonAdmin(): void
    {
        // Arrange
        $provider = $this->provider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($this->createStub(User::class), '1');
    }

    public function testGetIdentifierIsStable(): void
    {
        static::assertSame('core.image_report', $this->provider()->getIdentifier());
    }

    public function testApproveItemThrowsWhenReportNotFound(): void
    {
        // Arrange
        $provider = $this->provider();

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $provider->approveItem($this->createStub(User::class), '404');
    }

    public function testApproveItemResolvesAndPersistsReport(): void
    {
        // Arrange
        $report = $this->createMock(ImageReport::class);
        $report->expects($this->once())->method('setStatus')->with(ImageReportStatus::Resolved);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($report);
        $em->expects($this->once())->method('flush');
        $provider = $this->provider(found: $report, em: $em);

        // Act
        $provider->approveItem($this->createStub(User::class), '1');
    }

    public function testDenyItemIsRefusedAndLeavesTheReportOpen(): void
    {
        // Arrange
        $report = $this->createMock(ImageReport::class);
        $report->expects($this->never())->method('setStatus');
        $provider = $this->provider(found: $report);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $provider->denyItem($this->createStub(User::class), '1');
    }

    private function report(int $id, ?string $reporterName, ImageReportReason $reason, ?string $remarks, ?Image $image): ImageReport
    {
        $reporter = null;
        if ($reporterName !== null) {
            $reporter = $this->createStub(User::class);
            $reporter->method('getName')->willReturn($reporterName);
        }

        $report = $this->createStub(ImageReport::class);
        $report->method('getId')->willReturn($id);
        $report->method('getReporter')->willReturn($reporter);
        $report->method('getReason')->willReturn($reason);
        $report->method('getRemarks')->willReturn($remarks);
        $report->method('getImage')->willReturn($image);

        return $report;
    }

    /**
     * @param list<ImageReport> $openReports
     * @param array{label: string, route: string|null, params: array<string, mixed>}|null $location
     */
    private function provider(
        array $openReports = [],
        bool $isAdmin = true,
        ?ImageReport $found = null,
        ?EntityManagerInterface $em = null,
        ?array $location = null,
    ): CoreImageReportProvider {
        $repo = $this->createStub(ImageReportRepository::class);
        $repo->method('getOpen')->willReturn($openReports);
        $repo->method('find')->willReturn($found);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);
        $locations = $this->createStub(ImageLocationService::class);
        $locations->method('locate')->willReturn($location);
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn(string $route, array $params): string => '/admin/support/reports/' . $params['id']);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $id, array $params = []): string => implode('|', [$id, ...array_values($params)]));

        return new CoreImageReportProvider($repo, $em ?? $this->createStub(EntityManagerInterface::class), $security, $locations, $router, $translator);
    }
}
