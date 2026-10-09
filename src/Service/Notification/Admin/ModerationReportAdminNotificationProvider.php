<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Entity\User;
use App\Repository\ModerationReportRepository;
use DateTimeImmutable;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

readonly class ModerationReportAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private ModerationReportRepository $repository,
    ) {}

    public function getSection(): TranslatableInterface
    {
        return new TranslatableMessage('notifications.section_moderation_reports');
    }

    public function getPendingItems(User $recipient): array
    {
        $items = [];
        foreach ($this->repository->findOpenNewestFirst() as $report) {
            $items[] = new AdminNotificationItem(
                label: new TranslatableMessage('notifications.item_moderation_report', [
                    '%subject%' => $report->getSubjectLabel(),
                    '%reason%' => new TranslatableMessage($report->getReason()->label()),
                ]),
                route: 'app_admin_support_moderation_show',
                routeParams: ['id' => $report->getId()],
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        $reports = $this->repository->findOpenNewestFirst();

        return $reports !== [] ? $reports[0]->getCreatedAt() : null;
    }
}
