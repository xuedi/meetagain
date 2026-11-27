<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\AccountDeletionFeedback;
use App\Enum\AccountDeletionReason;
use App\Repository\AccountDeletionFeedbackRepository;
use App\Service\Security\ContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

readonly class DeletionFeedbackService
{
    public function __construct(
        private AccountDeletionFeedbackRepository $repository,
        private EntityManagerInterface $em,
        private ContentSanitizer $contentSanitizer,
        private ClockInterface $clock,
    ) {}

    public function record(AccountDeletionReason $reason, ?string $comment, string $locale): void
    {
        $plain = $this->contentSanitizer->toPlainText((string) $comment);

        $this->em->persist(new AccountDeletionFeedback($reason, $plain === '' ? null : $plain, $locale, $this->clock->now()));
        $this->em->flush();
    }

    /** @return list<AccountDeletionFeedback> */
    public function findNewestFirst(): array
    {
        return $this->repository->findNewestFirst();
    }

    /** @return list<array{reason: AccountDeletionReason, total: int}> */
    public function countByReason(): array
    {
        return $this->repository->countByReason();
    }
}
