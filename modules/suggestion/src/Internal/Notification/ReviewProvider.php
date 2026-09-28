<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Notification;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use InvalidArgumentException;
use Module\Suggestion\Internal\Entity\Suggestion;
use Module\Suggestion\Internal\SuggestionService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ReviewProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private SuggestionService $service,
        private RouterInterface $router,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'suggestions';
    }

    public function getReviewItems(User $user): array
    {
        $items = [];
        foreach ($this->service->pendingReviewableBy($user) as $suggestion) {
            $items[] = new ReviewNotificationItem(
                id: (string) $suggestion->getId(),
                description: $this->translator->trans('profile_review.suggestion_description', [
                    '%proposer%' => $suggestion->getProposedBy()->getName() ?? '',
                    '%target%' => $this->service->describe($suggestion),
                ]),
                canDeny: true,
                icon: 'lightbulb',
                longDescription: $this->summary($suggestion),
                detailUrl: $this->router->generate('app_review_suggestion', ['id' => $suggestion->getId()]),
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $suggestion = $this->pendingSuggestion($itemId);
        $this->service->approve($suggestion, $this->service->draftFor($suggestion), $user);
    }

    public function denyItem(User $user, string $itemId): void
    {
        $this->service->reject($this->pendingSuggestion($itemId), $user);
    }

    private function pendingSuggestion(string $itemId): Suggestion
    {
        $suggestion = $this->service->get((int) $itemId);
        if ($suggestion === null || !$suggestion->isPending()) {
            throw new InvalidArgumentException('Suggestion not found.');
        }

        return $suggestion;
    }

    private function summary(Suggestion $suggestion): string
    {
        $lines = [];
        foreach ($this->service->summaryRows($suggestion) as $row) {
            $lines[] = sprintf('%s: %s', $row['label'], $row['value'] !== '' ? $row['value'] : '-');
        }

        return implode("\n", $lines);
    }
}
