<?php declare(strict_types=1);

namespace Tests\Unit\Item\Report;

use App\Emails\Types\ItemReportDecisionEmail;
use App\Emails\Types\ItemReportReceiptEmail;
use App\Entity\ItemReport;
use App\Entity\User;
use App\Enum\ItemReportReason;
use App\Enum\ItemReportRelationship;
use App\Enum\ItemReportStatus;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Item\Report\ReportableTypeProviderInterface;
use App\Item\Report\ReportService;
use App\Repository\ItemReportRepository;
use App\Service\Config\PluginService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    /**
     * @param list<string> $activePlugins
     * @param list<int>|null $allowedIds
     */
    #[DataProvider('visibility')]
    public function testOnlyAVisibleItemOfAnActiveReportableTypeCanBeReported(string $type, array $activePlugins, ?array $allowedIds, ?string $expected): void
    {
        // Arrange
        $service = $this->service(activePlugins: $activePlugins, frontendIds: $allowedIds);

        // Act
        $label = $service->visibleItemLabel($type, 5);

        // Assert
        static::assertSame($expected, $label);
    }

    /** @return iterable<string, array{string, list<string>, list<int>|null, ?string}> */
    public static function visibility(): iterable
    {
        yield 'a visible song is reportable' => ['song', ['karaoke'], null, '茉莉花'];
        yield 'a song the item filter narrows away is not' => ['song', ['karaoke'], [1, 2], null];
        yield 'a song of an inactive plugin is not' => ['song', ['films'], null, null];
        yield 'a type that did not opt in is not' => ['film', ['karaoke', 'films'], null, null];
    }

    public function testCreatingAReportSendsTheReceipt(): void
    {
        // Arrange
        $receipt = $this->createMock(ItemReportReceiptEmail::class);
        $receipt
            ->expects($this->once())
            ->method('send')
            ->with(static::callback(static fn(array $context): bool => $context['report'] instanceof ItemReport && $context['itemPath'] === '/en/karaoke/5'));
        $service = $this->service(receipt: $receipt);

        // Act
        $report = $service->create(
            'song',
            5,
            '茉莉花',
            ItemReportReason::Copyright,
            ItemReportRelationship::Rightsholder,
            'This is my recording.',
            'Ada',
            'ada@example.org',
            null,
            'en',
        );

        // Assert
        static::assertTrue($report->isOpen());
        static::assertTrue($report->isGoodFaith());
    }

    public function testDeletingTheItemResolvesEveryOpenReportAndTellsEachNotifier(): void
    {
        // Arrange
        $reports = [$this->report(), $this->report()];
        $decision = $this->createMock(ItemReportDecisionEmail::class);
        $decision->expects($this->exactly(2))->method('send');
        $service = $this->service(openForItem: $reports, decision: $decision);

        // Act
        $service->resolveRemovedItem('song', 5, null);

        // Assert
        static::assertSame(
            [ItemReportStatus::Removed, ItemReportStatus::Removed],
            array_map(static fn(ItemReport $report): ItemReportStatus => $report->getStatus(), $reports),
        );
    }

    public function testKeepingResolvesTheReportAsKept(): void
    {
        // Arrange
        $report = $this->report();
        $resolver = new User();

        // Act
        $this->service()->keep($report, $resolver);

        // Assert
        static::assertSame(ItemReportStatus::Kept, $report->getStatus());
        static::assertSame($resolver, $report->getResolvedBy());
        static::assertNotNull($report->getResolvedAt());
    }

    public function testReviewersOnlySeeReportsOnItemsTheyManage(): void
    {
        // Arrange
        $managed = $this->report(5);
        $foreign = $this->report(9);
        $service = $this->service(adminIds: [5], open: [$managed, $foreign]);

        // Act
        $reviewable = $service->reviewable();

        // Assert
        static::assertSame([$managed], $reviewable);
    }

    /**
     * @param list<string> $activePlugins
     * @param list<int>|null $frontendIds
     * @param list<int>|null $adminIds
     * @param list<ItemReport> $open
     * @param list<ItemReport> $openForItem
     */
    private function service(
        array $activePlugins = ['karaoke'],
        ?array $frontendIds = null,
        ?array $adminIds = null,
        array $open = [],
        array $openForItem = [],
        ?ItemReportReceiptEmail $receipt = null,
        ?ItemReportDecisionEmail $decision = null,
    ): ReportService {
        $provider = $this->createStub(ReportableTypeProviderInterface::class);
        $provider->method('getPluginKey')->willReturn('karaoke');
        $provider->method('getTypeKey')->willReturn('song');
        $provider->method('getItemLabel')->willReturn('茉莉花');
        $provider->method('getItemPath')->willReturnCallback(static fn(int $id): string => '/en/karaoke/' . $id);

        $plugins = $this->createStub(PluginService::class);
        $plugins->method('getActiveList')->willReturn($activePlugins);
        $frontend = $this->createStub(FilterService::class);
        $frontend->method('getAllowedItemIds')->willReturn($frontendIds);
        $admin = $this->createStub(AdminFilterService::class);
        $admin->method('getAllowedItemIds')->willReturn($adminIds);
        $repo = $this->createStub(ItemReportRepository::class);
        $repo->method('findOpen')->willReturn($open);
        $repo->method('findOpenForItem')->willReturn($openForItem);

        return new ReportService(
            [$provider],
            $plugins,
            $frontend,
            $admin,
            $repo,
            $this->createStub(EntityManagerInterface::class),
            $receipt ?? $this->createStub(ItemReportReceiptEmail::class),
            $decision ?? $this->createStub(ItemReportDecisionEmail::class),
        );
    }

    private function report(int $itemId = 5): ItemReport
    {
        return new ItemReport()
            ->setItemType('song')
            ->setItemId($itemId)
            ->setItemLabel('茉莉花')
            ->setReason(ItemReportReason::Copyright)
            ->setRelationship(ItemReportRelationship::Other)
            ->setExplanation('Not theirs to share.')
            ->setNotifierName('Ada')
            ->setNotifierEmail('ada@example.org');
    }
}
