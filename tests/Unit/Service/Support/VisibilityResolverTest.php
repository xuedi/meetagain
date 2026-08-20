<?php declare(strict_types=1);

namespace Tests\Unit\Service\Support;

use App\Entity\SupportRequest;
use App\Enum\SupportAudience;
use App\Filter\Admin\Support\AdminSupportListFilterInterface;
use App\Filter\Admin\Support\AdminSupportListFilterService;
use App\Repository\SupportRequestRepository;
use App\Service\Support\VisibilityResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class VisibilityResolverTest extends TestCase
{
    public function testAnAdminSeesEveryAudience(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: true, filters: []);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Admin)));
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer)));
    }

    public function testAStewardNeverSeesWhatWasAddressedToTheAdmins(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: []);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer)));
        static::assertFalse($resolver->canView($this->request(SupportAudience::Admin)));
    }

    public function testTheFilterChainNarrowsWhatAStewardCanView(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter([1, 2, 3]), $this->filter([3, 4])]);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer, id: 3)));
        static::assertFalse($resolver->canView($this->request(SupportAudience::Organizer, id: 1)));
    }

    public function testABlockingFilterHidesEverything(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter([])]);

        // Act & Assert
        static::assertFalse($resolver->canView($this->request(SupportAudience::Organizer, id: 7)));
        static::assertSame([], $resolver->getVisibleRequests());
        static::assertSame(0, $resolver->countNew());
    }

    public function testAFilterWithNoOpinionLeavesTheSetUnrestricted(): void
    {
        // Arrange
        $resolver = $this->makeResolver(isAdmin: false, filters: [$this->filter(null)]);

        // Act & Assert
        static::assertTrue($resolver->canView($this->request(SupportAudience::Organizer, id: 99)));
    }

    /** @param array<AdminSupportListFilterInterface> $filters */
    private function makeResolver(bool $isAdmin, array $filters): VisibilityResolver
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        $repository = $this->createStub(SupportRequestRepository::class);
        $repository->method('findForAdminList')->willReturn([]);
        $repository->method('countNew')->willReturn(5);

        return new VisibilityResolver(new AdminSupportListFilterService($filters), $repository, $security);
    }

    /** @param array<int>|null $ids */
    private function filter(?array $ids): AdminSupportListFilterInterface
    {
        $filter = $this->createStub(AdminSupportListFilterInterface::class);
        $filter->method('getRequestIdFilter')->willReturn($ids);

        return $filter;
    }

    private function request(SupportAudience $audience, int $id = 1): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getAudience')->willReturn($audience);
        $request->method('getId')->willReturn($id);

        return $request;
    }
}
