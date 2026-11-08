<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\ModerationReport;
use App\Entity\User;
use App\Enum\ModerationReportReason;
use App\Repository\ModerationReportRepository;
use App\Service\Notification\Admin\ModerationReportAdminNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ModerationReportAdminNotificationProviderTest extends TestCase
{
    public function testTheSectionIsTheModerationReportsHeading(): void
    {
        // Arrange
        $provider = new ModerationReportAdminNotificationProvider($this->createStub(ModerationReportRepository::class));

        // Act
        $section = $provider->getSection();

        // Assert
        static::assertInstanceOf(TranslatableMessage::class, $section);
        static::assertSame('notifications.section_moderation_reports', $section->getMessage());
    }

    public function testEveryOpenReportBecomesAnItemLinkingToItsReviewPage(): void
    {
        // Arrange
        $provider = $this->provider([
            $this->report(7, 'Comment by Anna', ModerationReportReason::Spam, '2026-11-05 10:00'),
            $this->report(3, 'Message from Ben', ModerationReportReason::Harassment, '2026-11-04 10:00'),
        ]);

        // Act
        $items = $provider->getPendingItems($this->createStub(User::class));

        // Assert
        static::assertCount(2, $items);
        static::assertSame('app_admin_support_moderation_show', $items[0]->route);
        static::assertSame(['id' => 7], $items[0]->routeParams);
        static::assertSame(['id' => 3], $items[1]->routeParams);

        $label = $items[1]->label;
        static::assertInstanceOf(TranslatableMessage::class, $label);
        static::assertSame('notifications.item_moderation_report', $label->getMessage());
        static::assertSame('Message from Ben', $label->getParameters()['%subject%']);
        static::assertSame('report.subject_reason_harassment', $label->getParameters()['%reason%']->getMessage());
    }

    public function testWithoutOpenReportsThereAreNoItemsAndNoLatestDate(): void
    {
        // Arrange
        $provider = $this->provider([]);

        // Act
        $items = $provider->getPendingItems($this->createStub(User::class));
        $latest = $provider->getLatestPendingAt();

        // Assert
        static::assertSame([], $items);
        static::assertNull($latest);
    }

    public function testTheLatestDateIsTheNewestReportsCreationTime(): void
    {
        // Arrange
        $newest = $this->report(7, 'Comment by Anna', ModerationReportReason::Other, '2026-11-05 10:00');
        $provider = $this->provider([$newest, $this->report(3, 'Message from Ben', ModerationReportReason::Spam, '2026-11-01 10:00')]);

        // Act
        $latest = $provider->getLatestPendingAt();

        // Assert
        static::assertSame($newest->getCreatedAt(), $latest);
    }

    /**
     * @param list<ModerationReport> $reports
     */
    private function provider(array $reports): ModerationReportAdminNotificationProvider
    {
        $repository = $this->createStub(ModerationReportRepository::class);
        $repository->method('findOpenNewestFirst')->willReturn($reports);

        return new ModerationReportAdminNotificationProvider($repository);
    }

    private function report(int $id, string $subjectLabel, ModerationReportReason $reason, string $createdAt): ModerationReport
    {
        $report = $this->createStub(ModerationReport::class);
        $report->method('getId')->willReturn($id);
        $report->method('getSubjectLabel')->willReturn($subjectLabel);
        $report->method('getReason')->willReturn($reason);
        $report->method('getCreatedAt')->willReturn(new DateTimeImmutable($createdAt));

        return $report;
    }
}
