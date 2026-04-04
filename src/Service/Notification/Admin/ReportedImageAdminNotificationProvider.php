<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Repository\ImageReportRepository;
use DateTimeImmutable;

readonly class ReportedImageAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private ImageReportRepository $imageReportRepository,
    ) {}

    public function getSection(): string
    {
        return 'Reported Images';
    }

    public function getPendingItems(): array
    {
        $reports = $this->imageReportRepository->getOpen();
        $items = [];

        foreach ($reports as $report) {
            $items[] = new AdminNotificationItem(
                label: sprintf('Image #%s reported for: %s', $report->getImage()?->getId() ?? 'deleted', $report->getReason()->name),
                route: 'app_admin_support_reports',
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        $reports = $this->imageReportRepository->getOpen();

        return $reports !== [] ? $reports[0]->getCreatedAt() : null;
    }
}
