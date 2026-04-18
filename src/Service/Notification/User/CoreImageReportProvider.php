<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class CoreImageReportProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private ImageReportRepository $imageReportRepo,
        private EntityManagerInterface $em,
        private Security $security,
    ) {}


    public function getIdentifier(): string
    {
        return 'core.image_report';
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        $reports = $this->imageReportRepo->getOpen();
        $items = [];

        foreach ($reports as $report) {
            $reporterName = $report->getReporter()?->getName() ?? 'unknown';
            $items[] = new ReviewNotificationItem(
                id: (string) $report->getId(),
                description: sprintf('Image reported by %s', $reporterName),
                canDeny: true,
                icon: 'flag',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins can resolve image reports.');
        }

        $report = $this->imageReportRepo->find((int) $itemId);
        if ($report === null) {
            throw new InvalidArgumentException('Image report not found.');
        }

        $report->setStatus(ImageReportStatus::Resolved);
        $this->em->persist($report);
        $this->em->flush();
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins can dismiss image reports.');
        }

        $report = $this->imageReportRepo->find((int) $itemId);
        if ($report === null) {
            throw new InvalidArgumentException('Image report not found.');
        }

        $report->setStatus(ImageReportStatus::Resolved);
        $this->em->persist($report);
        $this->em->flush();
    }
}
