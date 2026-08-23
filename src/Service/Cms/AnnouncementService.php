<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Emails\Types\AnnouncementEmail;
use App\Entity\Announcement;
use App\Entity\BlockType\Gallery as GalleryType;
use App\Entity\BlockType\Text as TextType;
use App\Entity\BlockType\TextMap as TextMapType;
use App\Entity\Cms;
use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\EmailType;
use App\Filter\Email\AudienceFilterService;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailTemplateService;
use App\Service\Http\RequestHostResolver;
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
        private AnnouncementEmail $announcementEmail,
        private RequestHostResolver $hostResolver,
        private AudienceFilterService $audience,
    ) {}

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
        $announcementUrl = $this->hostResolver->getSchemeAndHost() . '/announcement/' . $announcement->getLinkHash();

        foreach ($subscribers as $subscriber) {
            $renderedContent = $this->renderContent($cmsPage, $subscriber->getLocale());
            $this->announcementEmail->send([
                'user' => $subscriber,
                'renderedContent' => $renderedContent,
                'announcementUrl' => $announcementUrl,
            ]);
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
        $subscribers = $this->audience->installationWideAudience($this->userRepo->findAnnouncementSubscribers());

        return array_filter($subscribers, static fn(User $user) => $user->getNotificationSettings()->isActive('announcements'));
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
        $title = $cmsPage->getPageTitle($locale) ?? "ERROR: The CMS page has no title for the language [{$locale}]";
        $contentParts = [];

        foreach ($cmsPage->getBlocks() as $block) {
            if ($block->getLanguage() !== $locale) {
                continue;
            }

            match ($block->getType()) {
                CmsBlockType::Text => $contentParts[] = '<p>' . TextType::fromJson($block->getJson())->content . '</p>',
                CmsBlockType::Gallery => $contentParts[] = $this->renderGalleryBlock(GalleryType::fromJson($block->getJson())),
                CmsBlockType::TextMap => $contentParts[] = $this->renderTextMapBlock(TextMapType::fromJson($block->getJson())),
                default => null,
            };
        }

        if ($contentParts === []) {
            $contentParts[] = "ERROR: The CMS page has no content for the language [{$locale}]";
        }

        return [
            'title' => $title,
            'content' => implode("\n", array_filter($contentParts)),
        ];
    }

    private function renderTextMapBlock(TextMapType $mapBlock): string
    {
        $parts = ['<p>' . $mapBlock->content . '</p>'];
        if ($mapBlock->hasMap()) {
            $url = sprintf(
                'https://www.openstreetmap.org/?mlat=%s&amp;mlon=%s#map=%d/%s/%s',
                $mapBlock->latitude,
                $mapBlock->longitude,
                $mapBlock->zoom,
                $mapBlock->latitude,
                $mapBlock->longitude,
            );
            $label = $mapBlock->markerLabel !== '' ? $mapBlock->markerLabel : 'OpenStreetMap';
            $parts[] = sprintf(
                '<p><a href="%s">%s</a></p>',
                $url,
                htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
        }

        return implode("\n", $parts);
    }

    private function renderGalleryBlock(GalleryType $galleryBlock): string
    {
        $parts = [];
        foreach ($galleryBlock->images as $item) {
            $url = $this->configService->getHost() . '/images/thumbnails/' . $item['hash'] . '_600x400.webp';
            $parts[] = sprintf('<p><img src="%s" alt="" style="max-width: 100%%; height: auto;"></p>', $url);
        }

        return implode("\n", $parts);
    }

    public function getPreviewContext(Announcement $announcement, string $locale = 'en'): array
    {
        $linkHash = $announcement->getLinkHash() ?? 'preview-' . $announcement->getId();
        $cmsPage = $announcement->getCmsPage();

        $renderedContent = $cmsPage instanceof Cms ? $this->renderContent($cmsPage, $locale) : ['title' => null, 'content' => ''];

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
        $dbTemplate = $this->templateService->getTemplate(EmailType::Announcement->value);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException('Announcement email template not found in database. Run app:email-templates:seed command.');
        }

        $context = $this->getPreviewContext($announcement, $locale);

        return [
            'subject' => $this->templateService->renderContent($dbTemplate->getSubject($locale), $context),
            'body' => $this->templateService->renderContent($dbTemplate->getBody($locale), $context),
        ];
    }
}
