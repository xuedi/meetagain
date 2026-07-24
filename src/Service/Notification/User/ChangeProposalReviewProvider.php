<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Review\ChangeProposalService;
use InvalidArgumentException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ChangeProposalReviewProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private ChangeProposalService $service,
        private RouterInterface $router,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'change_proposals';
    }

    public function getReviewItems(User $user): array
    {
        $items = [];
        foreach ($this->service->pendingReviewableBy($user) as $proposal) {
            $items[] = new ReviewNotificationItem(
                id: (string) $proposal->getId(),
                description: $this->translator->trans('profile_review.change_proposal_description', [
                    '%proposer%' => $proposal->getProposedBy()->getName() ?? '',
                    '%target%' => $this->service->targetLabel($proposal->getTargetType(), $proposal->getTargetId()) ?? '',
                ]),
                canDeny: true,
                icon: 'edit',
                longDescription: $this->diffSummary($proposal),
                detailUrl: $this->router->generate('app_review_proposals', [
                    'targetType' => $proposal->getTargetType(),
                    'targetId' => $proposal->getTargetId(),
                ]),
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $this->service->approveAll($this->pendingProposal($itemId), $user);
    }

    public function denyItem(User $user, string $itemId): void
    {
        $this->service->rejectAll($this->pendingProposal($itemId), $user);
    }

    private function pendingProposal(string $itemId): ChangeProposal
    {
        $proposal = $this->service->get((int) $itemId);
        if ($proposal === null || !$proposal->isPending()) {
            throw new InvalidArgumentException('Change proposal not found.');
        }

        return $proposal;
    }

    private function diffSummary(ChangeProposal $proposal): string
    {
        $lines = [];
        foreach ($this->service->fieldRows($proposal) as $row) {
            $lines[] = sprintf(
                '%s: %s -> %s',
                $row['label'],
                $row['before'] !== '' ? $row['before'] : '-',
                $row['after'] !== '' ? $row['after'] : '-',
            );
        }

        return implode("\n", $lines);
    }
}
