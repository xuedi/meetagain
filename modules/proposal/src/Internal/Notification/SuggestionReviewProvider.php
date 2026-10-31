<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Notification;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use InvalidArgumentException;
use Module\Proposal\Internal\Entity\Suggestion;
use Module\Proposal\Internal\Suggestion\SuggestionService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SuggestionReviewProvider implements ReviewNotificationProviderInterface
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
                fields: $this->service->summaryRows($suggestion),
                detailUrl: $this->router->generate('app_review_suggestion', ['id' => $suggestion->getId()]),
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $suggestion = $this->pendingSuggestion($itemId);
        $this->service->inScope($suggestion, fn(): int => $this->service->approve($suggestion, $this->service->draftFor($suggestion), $user));
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
}
