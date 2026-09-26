<?php declare(strict_types=1);

namespace App\Item\Report;

use App\Emails\Types\ItemReportDecisionEmail;
use App\Emails\Types\ItemReportReceiptEmail;
use App\Entity\ItemReport;
use App\Entity\User;
use App\Enum\ItemReportReason;
use App\Enum\ItemReportRelationship;
use App\Enum\ItemReportStatus;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Repository\ItemReportRepository;
use App\Service\Config\PluginService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ReportService
{
    /**
     * @param iterable<ReportableTypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ReportableTypeProviderInterface::class)]
        private iterable $providers,
        private PluginService $pluginService,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private ItemReportRepository $repo,
        private EntityManagerInterface $em,
        private ItemReportReceiptEmail $receiptEmail,
        private ItemReportDecisionEmail $decisionEmail,
    ) {}

    public function visibleItemLabel(string $itemType, int $itemId): ?string
    {
        $provider = $this->provider($itemType);
        if ($provider === null || !$this->isAllowed($this->itemFilter->getAllowedItemIds($itemType), $itemId)) {
            return null;
        }

        return $provider->getItemLabel($itemId);
    }

    public function itemPath(string $itemType, int $itemId): ?string
    {
        return $this->provider($itemType)?->getItemPath($itemId);
    }

    public function create(
        string $itemType,
        int $itemId,
        string $itemLabel,
        ItemReportReason $reason,
        ItemReportRelationship $relationship,
        string $explanation,
        string $notifierName,
        string $notifierEmail,
        ?User $reporter,
        string $locale,
    ): ItemReport {
        $report = new ItemReport()
            ->setItemType($itemType)
            ->setItemId($itemId)
            ->setItemLabel($itemLabel)
            ->setReason($reason)
            ->setRelationship($relationship)
            ->setExplanation($explanation)
            ->setNotifierName($notifierName)
            ->setNotifierEmail($notifierEmail)
            ->setReporter($reporter)
            ->setGoodFaith(true)
            ->setLocale($locale);

        $this->em->persist($report);
        $this->em->flush();

        $this->receiptEmail->send(['report' => $report, 'itemPath' => $this->itemPath($itemType, $itemId) ?? '']);

        return $report;
    }

    /** @return list<ItemReport> */
    public function reviewable(): array
    {
        return array_values(array_filter($this->repo->findOpen(), $this->isManageable(...)));
    }

    public function findReviewable(int $reportId): ?ItemReport
    {
        $report = $this->repo->find($reportId);

        return $report instanceof ItemReport && $report->isOpen() && $this->isManageable($report) ? $report : null;
    }

    public function keep(ItemReport $report, User $resolvedBy): void
    {
        $this->resolve([$report], ItemReportStatus::Kept, $resolvedBy);
    }

    public function resolveRemovedItem(string $itemType, int $itemId, ?User $resolvedBy): void
    {
        $this->resolve($this->repo->findOpenForItem($itemType, $itemId), ItemReportStatus::Removed, $resolvedBy);
    }

    /** @param list<ItemReport> $reports */
    private function resolve(array $reports, ItemReportStatus $status, ?User $resolvedBy): void
    {
        if ($reports === []) {
            return;
        }

        $now = new DateTimeImmutable();
        foreach ($reports as $report) {
            $report->resolve($status, $resolvedBy, $now);
        }
        $this->em->flush();

        foreach ($reports as $report) {
            $this->decisionEmail->send(['report' => $report]);
        }
    }

    private function isManageable(ItemReport $report): bool
    {
        $type = $report->getItemType();

        return $this->provider($type) !== null && $this->isAllowed($this->adminItemFilter->getAllowedItemIds($type), $report->getItemId());
    }

    /** @param list<int>|null $allowedIds */
    private function isAllowed(?array $allowedIds, int $itemId): bool
    {
        return $allowedIds === null || in_array($itemId, $allowedIds, true);
    }

    private function provider(string $itemType): ?ReportableTypeProviderInterface
    {
        $activePlugins = $this->pluginService->getActiveList();
        foreach ($this->providers as $provider) {
            $isActive = in_array($provider->getPluginKey(), $activePlugins, true);
            if ($provider->getTypeKey() === $itemType && $isActive) {
                return $provider;
            }
        }

        return null;
    }
}
