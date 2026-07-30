<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Emails\Types\AnnouncementEmail;
use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\EmailTemplate;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use App\Service\Cms\AnnouncementService;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailTemplateService;
use App\Service\Http\RequestHostResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AnnouncementServiceTest extends TestCase
{
    public function testSendThrowsExceptionWhenNotDraft(): void
    {
        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('isDraft')->willReturn(false);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement has already been sent');

        // Act
        $subject->send($announcement);
    }

    public function testSendThrowsExceptionWhenNoCmsPage(): void
    {
        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn(null);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement must have a CMS page linked before sending');

        // Act
        $subject->send($announcement);
    }

    public function testSendSuccessfullyProcessesSubscribers(): void
    {
        // Arrange
        $textBlock = $this->createStub(CmsBlock::class);
        $textBlock->method('getLanguage')->willReturn('en');
        $textBlock->method('getType')->willReturn(CmsBlockType::Text);
        $textBlock->method('getJson')->willReturn(['content' => 'Test content']);
        $textBlock->method('getImage')->willReturn(null);

        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getPageTitle')->willReturn('Test Title');
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$textBlock]));

        // Arrange
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn($cmsPage);
        $announcement->expects($this->once())->method('setLinkHash');
        $announcement->expects($this->once())->method('setStatus')->with(AnnouncementStatus::Sent);
        $announcement->expects($this->once())->method('setSentAt');
        $announcement->expects($this->once())->method('setRecipientCount')->with(2);

        // Arrange
        $notificationSettings = $this->createStub(NotificationSettings::class);
        $notificationSettings->method('isActive')->willReturn(true);

        // Arrange
        $subscriber1 = $this->createStub(User::class);
        $subscriber1->method('getLocale')->willReturn('en');
        $subscriber1->method('getNotificationSettings')->willReturn($notificationSettings);

        $subscriber2 = $this->createStub(User::class);
        $subscriber2->method('getLocale')->willReturn('en');
        $subscriber2->method('getNotificationSettings')->willReturn($notificationSettings);

        // Arrange
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->once())->method('findAnnouncementSubscribers')->willReturn([$subscriber1, $subscriber2]);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        // Arrange
        $announcementEmailMock = $this->createMock(AnnouncementEmail::class);
        $announcementEmailMock->expects($this->exactly(2))->method('send');

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($announcement);
        $emMock->expects($this->once())->method('flush');

        $subject = new AnnouncementService(
            em: $emMock,
            userRepo: $userRepoMock,
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $announcementEmailMock,
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->send($announcement);

        // Assert
        static::assertSame(2, $result);
    }

    public function testSendFiltersOutUsersWithDisabledNotifications(): void
    {
        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getPageTitle')->willReturn('Test Title');
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([]));

        // Arrange
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn($cmsPage);
        $announcement->expects($this->once())->method('setRecipientCount')->with(1);

        // Arrange
        $enabledSettings = $this->createStub(NotificationSettings::class);
        $enabledSettings->method('isActive')->willReturn(true);

        $disabledSettings = $this->createStub(NotificationSettings::class);
        $disabledSettings->method('isActive')->willReturn(false);

        // Arrange
        $enabledSubscriber = $this->createStub(User::class);
        $enabledSubscriber->method('getLocale')->willReturn('en');
        $enabledSubscriber->method('getNotificationSettings')->willReturn($enabledSettings);

        $disabledSubscriber = $this->createStub(User::class);
        $disabledSubscriber->method('getNotificationSettings')->willReturn($disabledSettings);

        // Arrange
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->once())->method('findAnnouncementSubscribers')->willReturn([$enabledSubscriber, $disabledSubscriber]);

        // Arrange
        $announcementEmailMock = $this->createMock(AnnouncementEmail::class);
        $announcementEmailMock->expects($this->once())->method('send');

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $userRepoMock,
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $announcementEmailMock,
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->send($announcement);

        // Assert
        static::assertSame(1, $result);
    }

    public function testGetPreviewContextReturnsCorrectData(): void
    {
        // Arrange
        $textBlock = $this->createStub(CmsBlock::class);
        $textBlock->method('getLanguage')->willReturn('en');
        $textBlock->method('getType')->willReturn(CmsBlockType::Text);
        $textBlock->method('getJson')->willReturn(['content' => 'Preview content']);
        $textBlock->method('getImage')->willReturn(null);

        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getPageTitle')->willReturn('Preview Title');
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$textBlock]));

        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(123);
        $announcement->method('getLinkHash')->willReturn('abc123hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertSame('Preview Title', $result['title']);
        static::assertStringContainsString('Preview content', $result['content']);
        static::assertSame('https://example.com/announcement/abc123hash', $result['announcementUrl']);
        static::assertSame('User', $result['username']);
        static::assertSame('https://example.com', $result['host']);
        static::assertSame('en', $result['lang']);
    }

    public function testGetPreviewContextUsesPreviewHashWhenLinkHashIsNull(): void
    {
        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(42);
        $announcement->method('getLinkHash')->willReturn(null);
        $announcement->method('getCmsPage')->willReturn(null);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertSame('https://example.com/announcement/preview-42', $result['announcementUrl']);
    }

    public function testGetPreviewContextHandlesMissingCmsPage(): void
    {
        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn(null);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertNull($result['title']);
        static::assertSame('', $result['content']);
    }

    public function testRenderPreviewThrowsExceptionWhenTemplateNotFound(): void
    {
        // Arrange
        $announcement = $this->createStub(Announcement::class);

        // Arrange
        $templateServiceMock = $this->createMock(EmailTemplateService::class);
        $templateServiceMock->expects($this->once())->method('getTemplate')->with(EmailType::Announcement->value)->willReturn(null);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $templateServiceMock,
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement email template not found in database');

        // Act
        $subject->renderPreview($announcement);
    }

    public function testRenderPreviewReturnsRenderedSubjectAndBody(): void
    {
        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getPageTitle')->willReturn('My Title');
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([]));

        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash123');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange
        $emailTemplate = $this->createStub(EmailTemplate::class);
        $emailTemplate->method('getSubject')->willReturn('Subject: {{title}}');
        $emailTemplate->method('getBody')->willReturn('Body: {{content}}');

        // Arrange
        $templateServiceMock = $this->createMock(EmailTemplateService::class);
        $templateServiceMock->expects($this->once())->method('getTemplate')->with(EmailType::Announcement->value)->willReturn($emailTemplate);
        $templateServiceMock
            ->expects($this->exactly(2))
            ->method('renderContent')
            ->willReturnCallback(static fn(string $content) => str_replace(['{{title}}', '{{content}}'], ['My Title', ''], $content));

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $templateServiceMock,
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->renderPreview($announcement, 'en');

        // Assert
        static::assertArrayHasKey('subject', $result);
        static::assertArrayHasKey('body', $result);
    }

    public function testRenderContentIncludesImageBlock(): void
    {
        // Arrange
        $imageBlock = $this->createStub(CmsBlock::class);
        $imageBlock->method('getLanguage')->willReturn('en');
        $imageBlock->method('getType')->willReturn(CmsBlockType::Gallery);
        $imageBlock
            ->method('getJson')
            ->willReturn([
                'title' => '',
                'images' => [['id' => 1, 'hash' => 'imagehash123']],
            ]);

        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$imageBlock]));

        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertStringContainsString('imagehash123', $result['content']);
        static::assertStringContainsString('<img', $result['content']);
    }

    public function testRenderContentSkipsBlocksForDifferentLocale(): void
    {
        // Arrange
        $enBlock = $this->createStub(CmsBlock::class);
        $enBlock->method('getLanguage')->willReturn('en');
        $enBlock->method('getType')->willReturn(CmsBlockType::Text);
        $enBlock->method('getJson')->willReturn(['content' => 'English content']);
        $enBlock->method('getImage')->willReturn(null);

        $deBlock = $this->createStub(CmsBlock::class);
        $deBlock->method('getLanguage')->willReturn('de');
        $deBlock->method('getType')->willReturn(CmsBlockType::Text);
        $deBlock->method('getJson')->willReturn(['content' => 'German content']);
        $deBlock->method('getImage')->willReturn(null);

        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$enBlock, $deBlock]));

        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertStringContainsString('English content', $result['content']);
        static::assertStringNotContainsString('German content', $result['content']);
    }

    public function testRenderContentShowsErrorWhenNoContentForLocale(): void
    {
        // Arrange
        $deBlock = $this->createStub(CmsBlock::class);
        $deBlock->method('getLanguage')->willReturn('de');
        $deBlock->method('getType')->willReturn(CmsBlockType::Text);
        $deBlock->method('getJson')->willReturn(['content' => 'German content']);
        $deBlock->method('getImage')->willReturn(null);

        // Arrange
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$deBlock]));

        // Arrange
        $announcement = $this->createStub(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configService,
            templateService: $this->createStub(EmailTemplateService::class),
            announcementEmail: $this->createStub(AnnouncementEmail::class),
            hostResolver: $this->createStub(RequestHostResolver::class),
        );

        // Act
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert
        static::assertStringContainsString('ERROR', $result['content']);
        static::assertStringContainsString('[en]', $result['content']);
    }
}
