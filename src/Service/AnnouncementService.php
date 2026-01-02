<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\Cms;
use App\Entity\EmailQueue;
use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class AnnouncementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private ConfigService $configService,
        private EmailTemplateService $templateService,
    ) {
    }

    public function send(Announcement $announcement): int
    {
        if (!$announcement->isDraft()) {
            throw new RuntimeException('Announcement has already been sent');
        }

        $cmsPage = $announcement->getCmsPage();
        if (!$cmsPage instanceof Cms) {
            throw new RuntimeException('Announcement must have a CMS page linked before sending');
        }

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

    private function queueAnnouncementEmail(User $recipient, Announcement $announcement, string $announcementUrl): void
    {
        $dbTemplate = $this->templateService->getTemplate(EmailType::Announcement);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException('Announcement email template not found in database. Run app:email-templates:seed command.');
        }

        $locale = $recipient->getLocale();
        $context = [
            'announcement' => $announcement->getContent($locale),
            'announcementUrl' => $announcementUrl,
            'username' => $recipient->getName(),
            'host' => $this->configService->getHost(),
            'lang' => $locale,
        ];

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($this->configService->getMailerAddress()->toString());
        $emailQueue->setRecipient((string) $recipient->getEmail());
        $emailQueue->setLang($locale);
        $emailQueue->setContext($context);
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);
        $emailQueue->setTemplate(EmailType::Announcement);
        $emailQueue->setSubject($this->templateService->renderContent($dbTemplate->getSubject(), $context));
        $emailQueue->setRenderedBody($this->templateService->renderContent($dbTemplate->getBody(), $context));

        $this->em->persist($emailQueue);
    }

    public function getPreviewContext(Announcement $announcement, string $locale = 'en'): array
    {
        $linkHash = $announcement->getLinkHash() ?? 'preview-' . $announcement->getId();

        return [
            'announcement' => $announcement->getContent($locale),
            'announcementUrl' => $this->configService->getHost() . '/announcement/' . $linkHash,
            'username' => 'Preview User',
            'host' => $this->configService->getHost(),
            'lang' => $locale,
        ];
    }

    public function renderPreview(Announcement $announcement, string $locale = 'en'): array
    {
        $dbTemplate = $this->templateService->getTemplate(EmailType::Announcement);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException('Announcement email template not found in database. Run app:email-templates:seed command.');
        }

        $context = $this->getPreviewContext($announcement, $locale);

        return [
            'subject' => $this->templateService->renderContent($dbTemplate->getSubject(), $context),
            'body' => $this->templateService->renderContent($dbTemplate->getBody(), $context),
        ];
    }
}
