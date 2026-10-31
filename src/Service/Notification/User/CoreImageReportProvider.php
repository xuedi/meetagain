<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\ImageReport;
use App\Entity\User;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use App\Service\Media\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CoreImageReportProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private ImageReportRepository $imageReportRepo,
        private EntityManagerInterface $em,
        private Security $security,
        private ImageLocationService $imageLocationService,
        private RouterInterface $router,
        private TranslatorInterface $translator,
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

        $items = [];
        foreach ($this->imageReportRepo->getOpen() as $report) {
            $items[] = new ReviewNotificationItem(
                id: (string) $report->getId(),
                description: $this->translator->trans('profile_review.image_report_description', [
                    '%reporter%' => $report->getReporter()?->getName() ?? $this->translator->trans('profile_review.image_report_unknown_reporter'),
                    '%reason%' => $this->translator->trans($report->getReason()->label()),
                ]),
                canDeny: false,
                icon: 'flag',
                longDescription: $this->translator->trans('profile_review.image_report_remove_hint'),
                detailUrl: $this->router->generate('app_admin_support_report_show', ['id' => $report->getId()]),
                approveLabelKey: 'profile_review.button_keep_image',
                fields: $this->fields($report),
                image: $report->getImage(),
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $report = $this->openReport($itemId);
        $report->setStatus(ImageReportStatus::Resolved);
        $this->em->persist($report);
        $this->em->flush();
    }

    public function denyItem(User $user, string $itemId): void
    {
        $this->openReport($itemId);

        throw new InvalidArgumentException('An image report is resolved by keeping or deleting the image.');
    }

    private function openReport(string $itemId): ImageReport
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins can resolve image reports.');
        }

        $report = $this->imageReportRepo->find((int) $itemId);
        if ($report === null) {
            throw new InvalidArgumentException('Image report not found.');
        }

        return $report;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function fields(ImageReport $report): array
    {
        $fields = [];
        $remarks = trim((string) $report->getRemarks());
        if ($remarks !== '') {
            $fields[] = ['label' => $this->translator->trans('profile_review.image_report_field_remarks'), 'value' => $remarks];
        }
        $image = $report->getImage();
        $location = $image === null ? null : $this->imageLocationService->locate($image);
        if ($location !== null) {
            $fields[] = ['label' => $this->translator->trans('profile_review.image_report_field_location'), 'value' => $location['label']];
        }

        return $fields;
    }
}
