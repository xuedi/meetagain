<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\ItemReport;
use App\Entity\User;
use App\Item\Report\ReportService;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ItemReportReviewProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private ReportService $reportService,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'item_reports';
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $items = [];
        foreach ($this->reportService->reviewable() as $report) {
            $items[] = new ReviewNotificationItem(
                id: (string) $report->getId(),
                description: $this->translator->trans('profile_review.item_report_description', [
                    '%item%' => $report->getItemLabel(),
                    '%reason%' => $this->translator->trans($report->getReason()->label()),
                ]),
                canDeny: false,
                icon: 'flag',
                longDescription: $this->summary($report),
                detailUrl: $this->reportService->itemPath($report->getItemType(), $report->getItemId()),
                approveLabelKey: 'profile_review.button_keep_item',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $this->reportService->keep($this->reviewableReport($itemId), $user);
    }

    public function denyItem(User $user, string $itemId): void
    {
        throw new InvalidArgumentException('An item report is resolved by keeping or deleting the item.');
    }

    private function reviewableReport(string $itemId): ItemReport
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can resolve item reports.');
        }

        $report = $this->reportService->findReviewable((int) $itemId);
        if ($report === null) {
            throw new InvalidArgumentException('Item report not found.');
        }

        return $report;
    }

    private function summary(ItemReport $report): string
    {
        return implode("\n", [
            $this->translator->trans('profile_review.item_report_notifier', [
                '%name%' => $report->getNotifierName(),
                '%email%' => $report->getNotifierEmail(),
                '%relationship%' => $this->translator->trans($report->getRelationship()->label()),
            ]),
            $report->getExplanation(),
            $this->translator->trans('profile_review.item_report_remove_hint'),
        ]);
    }
}
