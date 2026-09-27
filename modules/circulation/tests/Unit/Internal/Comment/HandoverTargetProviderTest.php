<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Internal\Comment;

use App\Entity\User;
use DateTimeImmutable;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Internal\Comment\HandoverTargetProvider;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Circulation\Tests\Stub\Users;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HandoverTargetProviderTest extends TestCase
{
    public function testTheGiverMayComment(): void
    {
        // Arrange
        $giver = Users::withId(3);
        $provider = $this->provider($this->handover($giver, Users::withId(5)), $giver);

        // Act + Assert
        self::assertTrue($provider->canComment(1));
    }

    public function testTheReceiverMayComment(): void
    {
        // Arrange
        $receiver = Users::withId(5);
        $provider = $this->provider($this->handover(Users::withId(3), $receiver), $receiver);

        // Act + Assert
        self::assertTrue($provider->canComment(1));
    }

    public function testAThirdPartyMayNotComment(): void
    {
        // Arrange
        $provider = $this->provider($this->handover(Users::withId(3), Users::withId(5)), Users::withId(99));

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAClosedHandoverAcceptsNoFurtherMessages(): void
    {
        // Arrange
        $giver = Users::withId(3);
        $handover = $this->handover($giver, Users::withId(5));
        $handover->setStatus(HandoverStatus::Completed);
        $provider = $this->provider($handover, $giver);

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAGuestMayNotComment(): void
    {
        // Arrange
        $provider = $this->provider($this->handover(Users::withId(3), Users::withId(5)), null);

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAMissingHandoverHasNoReturnUrl(): void
    {
        // Arrange
        $provider = $this->provider(null, Users::withId(3));

        // Act + Assert
        self::assertNull($provider->getReturnUrl(1));
        self::assertFalse($provider->canComment(1));
    }

    private function provider(?Handover $handover, ?User $viewer): HandoverTargetProvider
    {
        $handovers = $this->createStub(HandoverRepository::class);
        $handovers->method('find')->willReturn($handover);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($viewer);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/en/circulation/handover/1');

        return new HandoverTargetProvider($handovers, $security, $urls);
    }

    private function handover(User $giver, User $receiver): Handover
    {
        $copy = new Copy('book-group-1', 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));

        return new Handover($copy, $giver, $receiver, new DateTimeImmutable('2026-08-05 09:00:00'));
    }
}
