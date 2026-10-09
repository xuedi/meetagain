<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\ImageReport;
use App\Enum\ImageReportStatus;
use App\Repository\ImageReportRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class ImageReportService
{
    public function __construct(
        private ImageReportRepository $repository,
        private EntityManagerInterface $em,
    ) {}

    /** @return ImageReport[] */
    public function findNewestFirst(?ImageReportStatus $status = null): array
    {
        return $this->repository->findNewestFirst($status);
    }

    public function find(int $id): ?ImageReport
    {
        return $this->repository->find($id);
    }

    public function resolve(ImageReport $report): void
    {
        $report->setStatus(ImageReportStatus::Resolved);
        $this->em->flush();
    }
}
