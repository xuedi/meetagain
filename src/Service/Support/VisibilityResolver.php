<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
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
    ) {}

    /**
     * @return SupportRequest[]
     */
    public function getVisibleRequests(): array
    {
        $ids = $this->listFilter->getRequestIdFilter();

        return $ids === [] ? [] : $this->requestRepo->findForAdminList($this->audienceScope(), $ids);
    }

    public function countNew(): int
    {
        $ids = $this->listFilter->getRequestIdFilter();

        return $ids === [] ? 0 : $this->requestRepo->countNew($this->audienceScope(), $ids);
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

    private function audienceScope(): ?SupportAudience
    {
        return $this->security->isGranted('ROLE_ADMIN') ? null : SupportAudience::Organizer;
    }
}
