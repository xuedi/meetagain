<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Notification;

use App\Entity\User;
use InvalidArgumentException;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Internal\Change\ChangeService;
use Module\Proposal\Internal\Entity\Change;
use Module\Proposal\Internal\Notification\ChangeReviewProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangeReviewProviderTest extends TestCase
{
    public function testReviewItemsCarryDiffSummaryAndDetailUrl(): void
    {
        // Arrange
        $proposal = $this->proposal(7);
        $service = $this->createStub(ChangeService::class);
        $service->method('pendingReviewableBy')->willReturn([$proposal]);
        $service->method('targetLabel')->willReturn('你好');
        $service
            ->method('fieldRows')
            ->willReturn([
                ['field' => 'phrase', 'label' => 'Phrase', 'before' => 'old', 'after' => 'new', 'resolution' => null],
                ['field' => 'pinyin', 'label' => 'Pinyin', 'before' => '', 'after' => 'nĭ hăo', 'resolution' => null],
            ]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/review/proposals/glossary/1');
        $provider = new ChangeReviewProvider($service, $router, $this->createStub(TranslatorInterface::class));

        // Act
        $items = $provider->getReviewItems($this->user(9));

        // Assert
        self::assertCount(1, $items);
        self::assertSame('7', $items[0]->id);
        self::assertSame('/review/proposals/glossary/1', $items[0]->detailUrl);
        self::assertSame("Phrase: old -> new\nPinyin: - -> nĭ hăo", $items[0]->longDescription);
    }

    public function testApproveDelegatesToApproveAll(): void
    {
        // Arrange
        $proposal = $this->proposal(7);
        $user = $this->user(9);
        $service = $this->createMock(ChangeService::class);
        $service->method('get')->willReturn($proposal);
        $service->expects(self::once())->method('approveAll')->with($proposal, $user);
        $provider = $this->makeProvider($service);

        // Act
        $provider->approveItem($user, '7');
    }

    public function testDenyDelegatesToRejectAll(): void
    {
        // Arrange
        $proposal = $this->proposal(7);
        $user = $this->user(9);
        $service = $this->createMock(ChangeService::class);
        $service->method('get')->willReturn($proposal);
        $service->expects(self::once())->method('rejectAll')->with($proposal, $user);
        $provider = $this->makeProvider($service);

        // Act
        $provider->denyItem($user, '7');
    }

    public function testUnknownProposalThrows(): void
    {
        // Arrange
        $service = $this->createStub(ChangeService::class);
        $service->method('get')->willReturn(null);
        $provider = $this->makeProvider($service);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $provider->approveItem($this->user(9), '404');
    }

    private function makeProvider(ChangeService $service): ChangeReviewProvider
    {
        return new ChangeReviewProvider($service, $this->createStub(RouterInterface::class), $this->createStub(TranslatorInterface::class));
    }

    private function proposal(int $id): Change
    {
        $proposal = new Change();
        new ReflectionProperty(Change::class, 'id')->setValue($proposal, $id);
        $proposal->setTargetType('glossary');
        $proposal->setTargetId(1);
        $proposal->setProposedBy($this->user(5));
        $proposal->setChanges([new FieldChange('phrase', 'old', 'new')]);

        return $proposal;
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
