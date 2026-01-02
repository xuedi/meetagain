<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\AnnouncementStatus;
use App\Entity\BlockType\Image as ImageType;
use App\Entity\BlockType\Text as TextType;
use App\Entity\BlockType\Title as TitleType;
use App\Entity\Cms;
use App\Entity\CmsBlockTypes;
use App\Entity\EmailTemplate;
use App\Entity\Image;
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
        private EmailService $emailService,
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
            $renderedContent = $this->renderContent($cmsPage, $subscriber->getLocale());
            $this->emailService->prepareAnnouncementEmail($subscriber, $renderedContent, $announcementUrl, flush: false);
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

    /**
     * @return array{title: string|null, content: string}
     */
    private function renderContent(Cms $cmsPage, string $locale): array
    {
        $title = "ERROR: The CMS page has no title for the language [$locale]";
        $contentParts = [];

        foreach ($cmsPage->getBlocks() as $block) {
            if ($block->getLanguage() !== $locale) {
                continue;
            }

            match ($block->getType()) {
                CmsBlockTypes::Title => $title = TitleType::fromJson($block->getJson())->title,
                CmsBlockTypes::Text => $contentParts[] = '<p>' . TextType::fromJson($block->getJson())->content . '</p>',
                CmsBlockTypes::Image => $contentParts[] = $this->renderImageBlock(ImageType::fromJson($block->getJson(), $block->getImage())),
                default => null,
            };
        }

        if($contentParts === []) {
            $contentParts[] =  "ERROR: The CMS page has no content for the language [$locale]";
        }

        return [
            'title' => $title,
            'content' => implode("\n", array_filter($contentParts)),
        ];
    }

    private function renderImageBlock(ImageType $imageBlock): string
    {
        $image = $imageBlock->image;
        if (!$image instanceof Image) {
            return '';
        }

        $url = $this->configService->getHost() . '/images/thumbnails/' . $image->getHash() . '_600x400.webp';
        $alt = htmlspecialchars($image->getAlt() ?? '', ENT_QUOTES, 'UTF-8');

        return sprintf('<p><img src="%s" alt="%s" style="max-width: 100%%; height: auto;"></p>', $url, $alt);
    }

    public function getPreviewContext(Announcement $announcement, string $locale = 'en'): array
    {
        $linkHash = $announcement->getLinkHash() ?? 'preview-' . $announcement->getId();
        $cmsPage = $announcement->getCmsPage();

        $renderedContent = $cmsPage instanceof Cms
            ? $this->renderContent($cmsPage, $locale)
            : ['title' => null, 'content' => ''];

        return [
            'title' => $renderedContent['title'],
            'content' => $renderedContent['content'],
            'announcementUrl' => $this->configService->getHost() . '/announcement/' . $linkHash,
            'username' => 'User',
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
