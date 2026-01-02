<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\Cms;
use App\Entity\CmsBlockTypes;
use App\Entity\EmailQueue;
use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\LanguageRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class AnnouncementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private LanguageRepository $languageRepo,
        private CmsBlockService $cmsBlockService,
        private ConfigService $configService,
        private EmailTemplateService $templateService,
    ) {
    }

    public function createAnnouncement(string $title, string $content, User $createdBy): Announcement
    {
        $announcement = new Announcement();
        $announcement->setTitle($title);
        $announcement->setContent($content);
        $announcement->setCreatedBy($createdBy);
        $announcement->setCreatedAt(new DateTimeImmutable());
        $announcement->setStatus(AnnouncementStatus::Draft);

        $this->em->persist($announcement);
        $this->em->flush();

        return $announcement;
    }

    public function send(Announcement $announcement, User $admin): int
    {
        if (!$announcement->isDraft()) {
            throw new RuntimeException('Announcement has already been sent');
        }

        $cmsPage = $this->createCmsPage($announcement, $admin);
        $announcement->setCmsPage($cmsPage);
        $announcement->setLinkHash($this->generateLinkHash());

        $subscribers = $this->getAnnouncementSubscribers();
        $recipientCount = 0;
        $announcementUrl = $this->configService->getHost() . '/announcement/' . $announcement->getLinkHash();

        foreach ($subscribers as $subscriber) {
            $this->queueAnnouncementEmail($subscriber, $announcement, $announcementUrl);
            ++$recipientCount;
        }

        $announcement->setStatus(AnnouncementStatus::Sent);
        $announcement->setSentAt(new DateTimeImmutable());
        $announcement->setRecipientCount($recipientCount);

        $this->em->persist($announcement);
        $this->em->flush();

        return $recipientCount;
    }

    public function createCmsPage(Announcement $announcement, User $admin): Cms
    {
        $cmsPage = new Cms();
        $cmsPage->setSlug('announcement-' . $announcement->getId());
        $cmsPage->setPublished(true);
        $cmsPage->setCreatedBy($admin);
        $cmsPage->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($cmsPage);
        $this->em->flush();

        $languages = $this->languageRepo->getEnabledCodes();
        foreach ($languages as $locale) {
            $this->cmsBlockService->createBlock(
                $cmsPage,
                $locale,
                CmsBlockTypes::Title,
                ['title' => $announcement->getTitle()]
            );

            $this->cmsBlockService->createBlock(
                $cmsPage,
                $locale,
                CmsBlockTypes::Text,
                ['content' => $announcement->getContent()]
            );
        }

        return $cmsPage;
    }

    /**
     * @return User[]
     */
    private function getAnnouncementSubscribers(): array
    {
        $subscribers = $this->userRepo->findAnnouncementSubscribers();

        return array_filter(
            $subscribers,
            fn (User $user) => $user->getNotificationSettings()->isActive('announcements')
        );
    }

    private function generateLinkHash(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function queueAnnouncementEmail(User $recipient, Announcement $announcement, string $cmsUrl): void
    {
        $dbTemplate = $this->templateService->getTemplate(EmailType::Announcement);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException('Announcement email template not found in database. Run app:email-templates:seed command.');
        }

        $context = [
            'title' => $announcement->getTitle(),
            'announcement' => $announcement->getContent(),
            'announcementLink' => $cmsUrl,
            'username' => $recipient->getName(),
            'host' => $this->configService->getHost(),
            'lang' => $recipient->getLocale(),
        ];

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($this->configService->getMailerAddress()->toString());
        $emailQueue->setRecipient((string) $recipient->getEmail());
        $emailQueue->setLang($recipient->getLocale());
        $emailQueue->setContext($context);
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);
        $emailQueue->setTemplate(EmailType::Announcement);
        $emailQueue->setSubject($this->templateService->renderContent($dbTemplate->getSubject(), $context));
        $emailQueue->setRenderedBody($this->templateService->renderContent($dbTemplate->getBody(), $context));

        $this->em->persist($emailQueue);
    }

    public function getPreviewContext(Announcement $announcement): array
    {
        $linkHash = $announcement->getLinkHash() ?? 'preview-' . $announcement->getId();

        return [
            'title' => $announcement->getTitle(),
            'announcement' => $announcement->getContent(),
            'announcementLink' => $this->configService->getHost() . '/announcement/' . $linkHash,
            'username' => 'Preview User',
            'host' => $this->configService->getHost(),
            'lang' => 'en',
        ];
    }

    public function renderPreview(Announcement $announcement): array
    {
        $dbTemplate = $this->templateService->getTemplate(EmailType::Announcement);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException('Announcement email template not found in database. Run app:email-templates:seed command.');
        }

        $context = $this->getPreviewContext($announcement);

        return [
            'subject' => $this->templateService->renderContent($dbTemplate->getSubject(), $context),
            'body' => $this->templateService->renderContent($dbTemplate->getBody(), $context),
        ];
    }
}
