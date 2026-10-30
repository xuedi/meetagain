<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Notification;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use InvalidArgumentException;
use Module\Proposal\Internal\Change\ChangeService;
use Module\Proposal\Internal\Entity\Change;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ChangeReviewProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private ChangeService $service,
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

    private function pendingProposal(string $itemId): Change
    {
        $proposal = $this->service->get((int) $itemId);
        if ($proposal === null || !$proposal->isPending()) {
            throw new InvalidArgumentException('Change proposal not found.');
        }

        return $proposal;
    }

    private function diffSummary(Change $proposal): string
    {
        $lines = [];
        foreach ($this->service->fieldRows($proposal) as $row) {
            $lines[] = sprintf('%s: %s -> %s', $row['label'], $row['before'] !== '' ? $row['before'] : '-', $row['after'] !== '' ? $row['after'] : '-');
        }

        return implode("\n", $lines);
    }
}
