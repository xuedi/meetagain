<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Entity\User;
use App\Repository\ImageReportRepository;
use DateTimeImmutable;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

readonly class ReportedImageAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private ImageReportRepository $imageReportRepository,
    ) {}

    public function getSection(): TranslatableInterface
    {
        return new TranslatableMessage('notifications.section_reported_images');
    }

    public function getPendingItems(User $recipient): array
    {
        $reports = $this->imageReportRepository->getOpen();
        $items = [];

        foreach ($reports as $report) {
            $imageId = $report->getImage()?->getId();
            $items[] = new AdminNotificationItem(label: new TranslatableMessage('notifications.item_reported_image', [
                '%image%' => $imageId === null ? new TranslatableMessage('notifications.image_deleted') : (string) $imageId,
                '%reason%' => new TranslatableMessage($report->getReason()->label()),
            ]), route: 'app_admin_support_reports');
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        $reports = $this->imageReportRepository->getOpen();

        return $reports !== [] ? $reports[0]->getCreatedAt() : null;
    }
}
