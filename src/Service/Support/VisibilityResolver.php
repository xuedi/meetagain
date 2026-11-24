<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Filter\Admin\Support\AdminSupportListFilterService;
use App\Repository\SupportRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class VisibilityResolver
{
    public function __construct(
        private AdminSupportListFilterService $listFilter,
        private SupportRequestRepository $requestRepo,
        private Security $security,
        private RecipientResolver $recipientResolver,
    ) {}

    /**
     * @return SupportRequest[]
     */
    public function getVisibleRequests(): array
    {
        $ids = $this->listFilter->getRequestIdFilter();

        return $ids === [] ? [] : $this->requestRepo->findForAdminList($this->audienceScope(), $ids);
    }

    /**
     * @return array{involved: int, elsewhere: int}
     */
    public function countWaiting(User $reader): array
    {
        $counts = ['involved' => 0, 'elsewhere' => 0];
        $ids = $this->listFilter->getRequestIdFilter();
        if ($ids === []) {
            return $counts;
        }

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $counts['involved'] = $this->requestRepo->countNew(SupportAudience::Organizer, $ids);

            return $counts;
        }

        $open = $this->requestRepo->findOpen(null, $ids);
        $recipients = $this->recipientResolver->resolveMany(array_values(array_filter(
            $open,
            static fn(SupportRequest $request): bool => !$request->isAddressedToAdmins(),
        )));

        foreach ($open as $request) {
            if (!$request->isAddressedToAdmins() && !$this->isAmong($reader, $recipients[(int) $request->getId()] ?? [])) {
                ++$counts['elsewhere'];
                continue;
            }
            $counts['involved'] += $request->isNew() ? 1 : 0;
        }

        return $counts;
    }

    public function canView(SupportRequest $request): bool
    {
        $audience = $this->audienceScope();
        if ($audience !== null && $request->getAudience() !== $audience) {
            return false;
        }

        $ids = $this->listFilter->getRequestIdFilter();

        return $ids === null || in_array($request->getId(), $ids, true);
    }

    public function canOpenInAdmin(SupportRequest $request): bool
    {
        return $this->security->isGranted('ROLE_STEWARD') && $this->canView($request);
    }

    /**
     * @param User[] $users
     */
    private function isAmong(User $reader, array $users): bool
    {
        foreach ($users as $user) {
            if ($user->getId() === $reader->getId()) {
                return true;
            }
        }

        return false;
    }

    private function audienceScope(): ?SupportAudience
    {
        return $this->security->isGranted('ROLE_ADMIN') ? null : SupportAudience::Organizer;
    }
}
