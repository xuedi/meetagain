<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\AnnouncementsSection;
use DateTimeImmutable;

final class AnnouncementsSectionTest extends SectionTestCase
{
    public function testAnAnnouncementSurvivesTheRoundTrip(): void
    {
        // Arrange
        $scope = new Scope(users: [3 => 'admin'], cmsIds: [5], announcementIds: [1]);
        $exported = new AnnouncementsSection($this->entityManager([$this->sourceAnnouncement()]))->export($scope, $this->images());

        $context = $this->context();
        $page = new Cms();
        $creator = new User();
        $context->mapRef(Cms::class, 5, $page);
        $context->mapRef(User::class, 'steward@example.org', $creator);

        // Act
        new AnnouncementsSection($this->entityManager())->import($exported, $context);

        // Assert
        $announcement = $this->onlyPersisted(Announcement::class);
        static::assertSame(AnnouncementStatus::Sent, $announcement->getStatus());
        static::assertSame('2030-01-06 10:00', $announcement->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertSame('2030-01-07 09:30', $announcement->getSentAt()?->format('Y-m-d H:i'));
        static::assertSame(12, $announcement->getRecipientCount());
        static::assertSame('abc123', $announcement->getLinkHash());
        static::assertSame($page, $announcement->getCmsPage());
        static::assertSame($creator, $announcement->getCreatedBy());
        static::assertSame(1, $context->toSummary()->get('announcements', Outcome::Created));
    }

    public function testACreatorOutsideTheScopeIsCreditedToTheSteward(): void
    {
        // Arrange
        $scope = new Scope(cmsIds: [5], stewardEmail: 'owner@example.org', announcementIds: [1]);

        // Act
        $exported = new AnnouncementsSection($this->entityManager([$this->sourceAnnouncement()]))->export($scope, $this->images());

        // Assert
        static::assertSame('owner@example.org', $exported[0]['creator_email']);
    }

    public function testAPageOutsideTheScopeIsNotReferenced(): void
    {
        // Arrange
        $scope = new Scope(users: [3 => 'admin'], announcementIds: [1]);

        // Act
        $exported = new AnnouncementsSection($this->entityManager([$this->sourceAnnouncement()]))->export($scope, $this->images());

        // Assert
        static::assertNull($exported[0]['cms_ref']);
    }

    public function testNoAnnouncementIdsExportNothing(): void
    {
        // Act
        $exported = new AnnouncementsSection($this->entityManager([$this->sourceAnnouncement()]))->export(new Scope(), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testALinkHashAnotherAnnouncementHoldsIsLeftEmpty(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        new AnnouncementsSection($this->entityManager([], new Announcement()))->import([['link_hash' => 'abc123']], $context);

        // Assert
        static::assertNull($this->onlyPersisted(Announcement::class)->getLinkHash());
    }

    public function testTheSecondRowWithTheSameLinkHashIsLeftEmpty(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        new AnnouncementsSection($this->entityManager())->import([['link_hash' => 'abc123'], ['link_hash' => 'abc123']], $context);

        // Assert
        static::assertSame(['abc123', null], array_map(static fn(object $announcement): ?string => $announcement instanceof Announcement ? $announcement->getLinkHash() : null, $this->persisted));
    }

    public function testAnUnknownCreatorFallsBackToTheSystemUserAndAnUnknownStatusToDraft(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        new AnnouncementsSection($this->entityManager())->import([['creator_email' => 'gone@example.org', 'status' => 'archived']], $context);

        // Assert
        $announcement = $this->onlyPersisted(Announcement::class);
        static::assertSame($context->getSystemUser(), $announcement->getCreatedBy());
        static::assertSame(AnnouncementStatus::Draft, $announcement->getStatus());
        static::assertNull($announcement->getCmsPage());
    }

    private function sourceAnnouncement(): Announcement
    {
        $creator = $this->withId(new User(), 3);
        $creator->setEmail('steward@example.org');

        $announcement = new Announcement();
        $announcement->setStatus(AnnouncementStatus::Sent);
        $announcement->setCreatedAt(new DateTimeImmutable('2030-01-06 10:00'));
        $announcement->setSentAt(new DateTimeImmutable('2030-01-07 09:30'));
        $announcement->setRecipientCount(12);
        $announcement->setLinkHash('abc123');
        $announcement->setCmsPage($this->withId(new Cms(), 5));
        $announcement->setCreatedBy($creator);

        return $announcement;
    }
}
