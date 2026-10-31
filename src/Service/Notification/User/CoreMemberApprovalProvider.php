<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Member\PendingApprovalFilterService;
use App\Repository\UserRepository;
use App\Service\Member\RegistrationApprovalProviderInterface;
use App\Service\Member\UserService;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CoreMemberApprovalProvider implements ReviewNotificationProviderInterface
{
    /**
     * @param iterable<RegistrationApprovalProviderInterface> $approvalProviders
     */
    public function __construct(
        private UserRepository $userRepo,
        private UserService $userService,
        private PendingApprovalFilterService $pendingApproval,
        private TranslatorInterface $translator,
        private RouterInterface $router,
        #[AutowireIterator(RegistrationApprovalProviderInterface::class)]
        private iterable $approvalProviders,
    ) {}

    public function getIdentifier(): string
    {
        return 'core.member_approval';
    }

    public function getReviewItems(User $user): array
    {
        $isAdmin = $user->getRole() === UserRole::Admin;
        $items = [];
        foreach ($this->pendingApproval->pendingFor($user) as $pendingUser) {
            $items[] = new ReviewNotificationItem(
                id: (string) $pendingUser->getId(),
                description: $this->translator->trans('profile_review.member_approval_description', [
                    '%name%' => $pendingUser->getName(),
                ]),
                canDeny: true,
                icon: 'user-check',
                detailUrl: $isAdmin ? $this->router->generate('app_admin_member_edit', ['id' => $pendingUser->getId()]) : null,
                fields: $this->fields($pendingUser, $isAdmin),
                image: $pendingUser->getImage(),
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $this->userService->transitionStatus($user, $this->reviewablePendingUser($user, $itemId), UserStatus::Active);
    }

    public function denyItem(User $user, string $itemId): void
    {
        $this->userService->transitionStatus($user, $this->reviewablePendingUser($user, $itemId), UserStatus::Denied);
    }

    private function reviewablePendingUser(User $reviewer, string $itemId): User
    {
        $pendingUser = $this->userRepo->find((int) $itemId);
        if ($pendingUser === null || $pendingUser->getStatus() !== UserStatus::EmailVerified) {
            throw new InvalidArgumentException('User not found or not pending approval.');
        }

        if (!$this->pendingApproval->mayReview($reviewer, $pendingUser)) {
            throw new AccessDeniedException('You may not decide on this member.');
        }

        return $pendingUser;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function fields(User $pendingUser, bool $isAdmin): array
    {
        $rows = [
            'profile_review.member_field_waiting_for' => $this->waitingFor($pendingUser),
            'profile_review.member_field_signed_up' => $pendingUser->getCreatedAt()?->format('Y-m-d'),
            'profile_review.member_field_email' => $isAdmin ? $pendingUser->getEmail() : null,
            'profile_review.member_field_about' => $pendingUser->getBio(),
        ];

        $fields = [];
        foreach ($rows as $label => $value) {
            if (trim((string) $value) !== '') {
                $fields[] = ['label' => $this->translator->trans($label), 'value' => (string) $value];
            }
        }

        return $fields;
    }

    private function waitingFor(User $pendingUser): ?string
    {
        foreach ($this->approvalProviders as $provider) {
            $label = $provider->waitingFor($pendingUser);
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }
}
