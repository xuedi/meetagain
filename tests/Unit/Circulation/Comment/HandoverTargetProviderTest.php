<?php declare(strict_types=1);

namespace Tests\Unit\Circulation\Comment;

use App\Circulation\Comment\HandoverTargetProvider;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\User;
use App\Enum\CirculationHandoverStatus;
use App\Repository\CirculationHandoverRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HandoverTargetProviderTest extends TestCase
{
    public function testTheGiverMayComment(): void
    {
        // Arrange
        $giver = $this->user(3);
        $provider = $this->provider($this->handover($giver, $this->user(5)), $giver);

        // Act + Assert
        self::assertTrue($provider->canComment(1));
    }

    public function testTheReceiverMayComment(): void
    {
        // Arrange
        $receiver = $this->user(5);
        $provider = $this->provider($this->handover($this->user(3), $receiver), $receiver);

        // Act + Assert
        self::assertTrue($provider->canComment(1));
    }

    public function testAThirdPartyMayNotComment(): void
    {
        // Arrange
        $provider = $this->provider($this->handover($this->user(3), $this->user(5)), $this->user(99));

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAClosedHandoverAcceptsNoFurtherMessages(): void
    {
        // Arrange
        $giver = $this->user(3);
        $handover = $this->handover($giver, $this->user(5));
        $handover->setStatus(CirculationHandoverStatus::Completed);
        $provider = $this->provider($handover, $giver);

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAGuestMayNotComment(): void
    {
        // Arrange
        $provider = $this->provider($this->handover($this->user(3), $this->user(5)), null);

        // Act + Assert
        self::assertFalse($provider->canComment(1));
    }

    public function testAMissingHandoverHasNoReturnUrl(): void
    {
        // Arrange
        $provider = $this->provider(null, $this->user(3));

        // Act + Assert
        self::assertNull($provider->getReturnUrl(1));
        self::assertFalse($provider->canComment(1));
    }

    private function provider(?CirculationHandover $handover, ?User $viewer): HandoverTargetProvider
    {
        $handovers = $this->createStub(CirculationHandoverRepository::class);
        $handovers->method('find')->willReturn($handover);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($viewer);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/en/circulation/handover/1');

        return new HandoverTargetProvider($handovers, $security, $urls);
    }

    private function handover(User $giver, User $receiver): CirculationHandover
    {
        $copy = new CirculationCopy('book-group-1', 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'));

        return new CirculationHandover($copy, $giver, $receiver, new DateTimeImmutable('2026-08-05 09:00:00'));
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
