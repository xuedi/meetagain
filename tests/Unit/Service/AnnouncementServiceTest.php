<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use App\Entity\EmailTemplate;
use App\Entity\Image;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use App\Service\AnnouncementService;
use App\Service\ConfigService;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AnnouncementServiceTest extends TestCase
{
    public function testSendThrowsExceptionWhenNotDraft(): void
    {
        // Arrange: create announcement that is already sent
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(false);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Assert: expect exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement has already been sent');

        // Act: try to send
        $subject->send($announcement);
    }

    public function testSendThrowsExceptionWhenNoCmsPage(): void
    {
        // Arrange: create draft announcement without CMS page
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn(null);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Assert: expect exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement must have a CMS page linked before sending');

        // Act: try to send
        $subject->send($announcement);
    }

    public function testSendSuccessfullyProcessesSubscribers(): void
    {
        // Arrange: create CMS blocks
        $titleBlock = $this->createMock(CmsBlock::class);
        $titleBlock->method('getLanguage')->willReturn('en');
        $titleBlock->method('getType')->willReturn(CmsBlockTypes::Title);
        $titleBlock->method('getJson')->willReturn(['title' => 'Test Title']);
        $titleBlock->method('getImage')->willReturn(null);

        $textBlock = $this->createMock(CmsBlock::class);
        $textBlock->method('getLanguage')->willReturn('en');
        $textBlock->method('getType')->willReturn(CmsBlockTypes::Text);
        $textBlock->method('getJson')->willReturn(['content' => 'Test content']);
        $textBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page with blocks
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$titleBlock, $textBlock]));

        // Arrange: create draft announcement with CMS page
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn($cmsPage);
        $announcement->expects($this->once())->method('setLinkHash');
        $announcement->expects($this->once())->method('setStatus')->with(AnnouncementStatus::Sent);
        $announcement->expects($this->once())->method('setSentAt');
        $announcement->expects($this->once())->method('setRecipientCount')->with(2);

        // Arrange: create notification settings that allow announcements
        $notificationSettings = $this->createMock(NotificationSettings::class);
        $notificationSettings->method('isActive')->with('announcements')->willReturn(true);

        // Arrange: create subscribers
        $subscriber1 = $this->createMock(User::class);
        $subscriber1->method('getLocale')->willReturn('en');
        $subscriber1->method('getNotificationSettings')->willReturn($notificationSettings);

        $subscriber2 = $this->createMock(User::class);
        $subscriber2->method('getLocale')->willReturn('en');
        $subscriber2->method('getNotificationSettings')->willReturn($notificationSettings);

        // Arrange: user repository returns subscribers
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('findAnnouncementSubscribers')
            ->willReturn([$subscriber1, $subscriber2]);

        // Arrange: config service returns host
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        // Arrange: email service should be called for each subscriber
        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->exactly(2))->method('prepareAnnouncementEmail');

        // Arrange: entity manager should persist and flush
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($announcement);
        $emMock->expects($this->once())->method('flush');

        $subject = new AnnouncementService(
            em: $emMock,
            userRepo: $userRepoMock,
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $emailServiceMock,
        );

        // Act: send announcement
        $result = $subject->send($announcement);

        // Assert: returns recipient count
        $this->assertSame(2, $result);
    }

    public function testSendFiltersOutUsersWithDisabledNotifications(): void
    {
        // Arrange: create CMS blocks
        $titleBlock = $this->createMock(CmsBlock::class);
        $titleBlock->method('getLanguage')->willReturn('en');
        $titleBlock->method('getType')->willReturn(CmsBlockTypes::Title);
        $titleBlock->method('getJson')->willReturn(['title' => 'Test Title']);
        $titleBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page with blocks
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$titleBlock]));

        // Arrange: create draft announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('isDraft')->willReturn(true);
        $announcement->method('getCmsPage')->willReturn($cmsPage);
        $announcement->expects($this->once())->method('setRecipientCount')->with(1);

        // Arrange: notification settings - one enabled, one disabled
        $enabledSettings = $this->createMock(NotificationSettings::class);
        $enabledSettings->method('isActive')->with('announcements')->willReturn(true);

        $disabledSettings = $this->createMock(NotificationSettings::class);
        $disabledSettings->method('isActive')->with('announcements')->willReturn(false);

        // Arrange: create subscribers
        $enabledSubscriber = $this->createMock(User::class);
        $enabledSubscriber->method('getLocale')->willReturn('en');
        $enabledSubscriber->method('getNotificationSettings')->willReturn($enabledSettings);

        $disabledSubscriber = $this->createMock(User::class);
        $disabledSubscriber->method('getNotificationSettings')->willReturn($disabledSettings);

        // Arrange: user repository returns both subscribers
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock
            ->expects($this->once())
            ->method('findAnnouncementSubscribers')
            ->willReturn([$enabledSubscriber, $disabledSubscriber]);

        // Arrange: email service should only be called once (for enabled subscriber)
        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->once())->method('prepareAnnouncementEmail');

        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $userRepoMock,
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $emailServiceMock,
        );

        // Act: send announcement
        $result = $subject->send($announcement);

        // Assert: only one recipient
        $this->assertSame(1, $result);
    }

    public function testGetPreviewContextReturnsCorrectData(): void
    {
        // Arrange: create CMS blocks
        $titleBlock = $this->createMock(CmsBlock::class);
        $titleBlock->method('getLanguage')->willReturn('en');
        $titleBlock->method('getType')->willReturn(CmsBlockTypes::Title);
        $titleBlock->method('getJson')->willReturn(['title' => 'Preview Title']);
        $titleBlock->method('getImage')->willReturn(null);

        $textBlock = $this->createMock(CmsBlock::class);
        $textBlock->method('getLanguage')->willReturn('en');
        $textBlock->method('getType')->willReturn(CmsBlockTypes::Text);
        $textBlock->method('getJson')->willReturn(['content' => 'Preview content']);
        $textBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$titleBlock, $textBlock]));

        // Arrange: create announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(123);
        $announcement->method('getLinkHash')->willReturn('abc123hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: context contains expected data
        $this->assertSame('Preview Title', $result['title']);
        $this->assertStringContainsString('Preview content', $result['content']);
        $this->assertSame('https://example.com/announcement/abc123hash', $result['announcementUrl']);
        $this->assertSame('User', $result['username']);
        $this->assertSame('https://example.com', $result['host']);
        $this->assertSame('en', $result['lang']);
    }

    public function testGetPreviewContextUsesPreviewHashWhenLinkHashIsNull(): void
    {
        // Arrange: create announcement without link hash
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(42);
        $announcement->method('getLinkHash')->willReturn(null);
        $announcement->method('getCmsPage')->willReturn(null);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: uses preview hash
        $this->assertSame('https://example.com/announcement/preview-42', $result['announcementUrl']);
    }

    public function testGetPreviewContextHandlesMissingCmsPage(): void
    {
        // Arrange: create announcement without CMS page
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn(null);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: returns null title and empty content
        $this->assertNull($result['title']);
        $this->assertSame('', $result['content']);
    }

    public function testRenderPreviewThrowsExceptionWhenTemplateNotFound(): void
    {
        // Arrange: create announcement
        $announcement = $this->createStub(Announcement::class);

        // Arrange: template service returns null
        $templateServiceMock = $this->createMock(EmailTemplateService::class);
        $templateServiceMock
            ->expects($this->once())
            ->method('getTemplate')
            ->with(EmailType::Announcement)
            ->willReturn(null);

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $this->createStub(ConfigService::class),
            templateService: $templateServiceMock,
            emailService: $this->createStub(EmailService::class),
        );

        // Assert: expect exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Announcement email template not found in database');

        // Act: try to render preview
        $subject->renderPreview($announcement);
    }

    public function testRenderPreviewReturnsRenderedSubjectAndBody(): void
    {
        // Arrange: create CMS blocks
        $titleBlock = $this->createMock(CmsBlock::class);
        $titleBlock->method('getLanguage')->willReturn('en');
        $titleBlock->method('getType')->willReturn(CmsBlockTypes::Title);
        $titleBlock->method('getJson')->willReturn(['title' => 'My Title']);
        $titleBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$titleBlock]));

        // Arrange: create announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash123');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange: create email template
        $emailTemplate = $this->createMock(EmailTemplate::class);
        $emailTemplate->method('getSubject')->with('en')->willReturn('Subject: {{title}}');
        $emailTemplate->method('getBody')->with('en')->willReturn('Body: {{content}}');

        // Arrange: template service
        $templateServiceMock = $this->createMock(EmailTemplateService::class);
        $templateServiceMock
            ->expects($this->once())
            ->method('getTemplate')
            ->with(EmailType::Announcement)
            ->willReturn($emailTemplate);
        $templateServiceMock
            ->expects($this->exactly(2))
            ->method('renderContent')
            ->willReturnCallback(fn (string $content) => str_replace(['{{title}}', '{{content}}'], ['My Title', ''], $content));

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $templateServiceMock,
            emailService: $this->createStub(EmailService::class),
        );

        // Act: render preview
        $result = $subject->renderPreview($announcement, 'en');

        // Assert: returns subject and body
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
    }

    public function testRenderContentIncludesImageBlock(): void
    {
        // Arrange: create image
        $image = $this->createMock(Image::class);
        $image->method('getHash')->willReturn('imagehash123');
        $image->method('getAlt')->willReturn('Test image');

        // Arrange: create image block
        $imageBlock = $this->createMock(CmsBlock::class);
        $imageBlock->method('getLanguage')->willReturn('en');
        $imageBlock->method('getType')->willReturn(CmsBlockTypes::Image);
        $imageBlock->method('getJson')->willReturn(['id' => 'img-1']);
        $imageBlock->method('getImage')->willReturn($image);

        // Arrange: create CMS page
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$imageBlock]));

        // Arrange: create announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context to trigger renderContent
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: content contains image HTML
        $this->assertStringContainsString('imagehash123', $result['content']);
        $this->assertStringContainsString('Test image', $result['content']);
        $this->assertStringContainsString('<img', $result['content']);
    }

    public function testRenderContentSkipsBlocksForDifferentLocale(): void
    {
        // Arrange: create blocks for different locales
        $enBlock = $this->createMock(CmsBlock::class);
        $enBlock->method('getLanguage')->willReturn('en');
        $enBlock->method('getType')->willReturn(CmsBlockTypes::Text);
        $enBlock->method('getJson')->willReturn(['content' => 'English content']);
        $enBlock->method('getImage')->willReturn(null);

        $deBlock = $this->createMock(CmsBlock::class);
        $deBlock->method('getLanguage')->willReturn('de');
        $deBlock->method('getType')->willReturn(CmsBlockTypes::Text);
        $deBlock->method('getJson')->willReturn(['content' => 'German content']);
        $deBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$enBlock, $deBlock]));

        // Arrange: create announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context for English
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: only English content is included
        $this->assertStringContainsString('English content', $result['content']);
        $this->assertStringNotContainsString('German content', $result['content']);
    }

    public function testRenderContentShowsErrorWhenNoContentForLocale(): void
    {
        // Arrange: create block for different locale only
        $deBlock = $this->createMock(CmsBlock::class);
        $deBlock->method('getLanguage')->willReturn('de');
        $deBlock->method('getType')->willReturn(CmsBlockTypes::Text);
        $deBlock->method('getJson')->willReturn(['content' => 'German content']);
        $deBlock->method('getImage')->willReturn(null);

        // Arrange: create CMS page
        $cmsPage = $this->createMock(Cms::class);
        $cmsPage->method('getBlocks')->willReturn(new ArrayCollection([$deBlock]));

        // Arrange: create announcement
        $announcement = $this->createMock(Announcement::class);
        $announcement->method('getId')->willReturn(1);
        $announcement->method('getLinkHash')->willReturn('hash');
        $announcement->method('getCmsPage')->willReturn($cmsPage);

        // Arrange: config service
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock->method('getHost')->willReturn('https://example.com');

        $subject = new AnnouncementService(
            em: $this->createStub(EntityManagerInterface::class),
            userRepo: $this->createStub(UserRepository::class),
            configService: $configServiceMock,
            templateService: $this->createStub(EmailTemplateService::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: get preview context for English (which has no content)
        $result = $subject->getPreviewContext($announcement, 'en');

        // Assert: error message is shown
        $this->assertStringContainsString('ERROR', $result['content']);
        $this->assertStringContainsString('[en]', $result['content']);
    }
}