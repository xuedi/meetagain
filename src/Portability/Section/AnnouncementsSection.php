<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Announcement;
use App\Entity\Cms;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class AnnouncementsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'announcements';
    }

    #[Override]
    public function getOrder(): int
    {
        return 52;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->announcementIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(Announcement::class)->findBy(['id' => $scope->announcementIds], ['id' => 'ASC']) as $announcement) {
            $pageId = $announcement->getCmsPage()?->getId();

            $rows[] = [
                'cms_ref' => in_array($pageId, $scope->cmsIds, true) ? $pageId : null,
                'status' => $announcement->getStatus()->value,
                'created_at' => $announcement->getCreatedAt()?->format(DateTimeInterface::ATOM),
                'sent_at' => $announcement->getSentAt()?->format(DateTimeInterface::ATOM),
                'recipient_count' => $announcement->getRecipientCount(),
                'link_hash' => $announcement->getLinkHash(),
                'creator_email' => $scope->creditEmail($announcement->getCreatedBy()),
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $takenHashes = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $linkHash = $this->freeLinkHash($row['link_hash'] ?? null, $takenHashes);
            if ($linkHash !== null) {
                $takenHashes[] = $linkHash;
            }

            $announcement = new Announcement();
            $announcement->setStatus(AnnouncementStatus::tryFrom((string) ($row['status'] ?? '')) ?? AnnouncementStatus::Draft);
            $announcement->setCreatedAt($this->readDate($row['created_at'] ?? null) ?? new DateTimeImmutable());
            $announcement->setSentAt($this->readDate($row['sent_at'] ?? null));
            $announcement->setRecipientCount(isset($row['recipient_count']) ? (int) $row['recipient_count'] : null);
            $announcement->setCmsPage($context->resolveRef(Cms::class, $row['cms_ref'] ?? null));
            $announcement->setCreatedBy($context->resolveRef(User::class, $row['creator_email'] ?? null) ?? $context->getSystemUser());
            $announcement->setLinkHash($linkHash);

            $this->em->persist($announcement);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param list<string> $takenHashes
     */
    private function freeLinkHash(mixed $linkHash, array $takenHashes): ?string
    {
        if (!is_string($linkHash) || $linkHash === '' || in_array($linkHash, $takenHashes, true)) {
            return null;
        }

        $holder = $this->em->getRepository(Announcement::class)->findOneBy(['linkHash' => $linkHash]);

        return $holder === null ? $linkHash : null;
    }

    private function readDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }
}
