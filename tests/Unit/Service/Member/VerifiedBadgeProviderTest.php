<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Member\VerifiedBadgeProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class VerifiedBadgeProviderTest extends TestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function makeProvider(): VerifiedBadgeProvider
    {
        return new VerifiedBadgeProvider($this->userRepository);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAVerifiedMemberIsBadgedWithATranslationKeyNotDisplayText(): void
    {
        // Arrange
        $user = new User();
        $user->setVerified(true);
        $this->userRepository->method('find')->willReturn($user);

        // Act
        $badges = $this->makeProvider()->getBadges(7);

        // Assert
        $this->assertCount(1, $badges);
        $this->assertSame('member.badge_verified', $badges[0]->title);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnUnverifiedMemberGetsNoBadge(): void
    {
        // Arrange
        $user = new User();
        $user->setVerified(false);
        $this->userRepository->method('find')->willReturn($user);

        // Act + Assert
        $this->assertSame([], $this->makeProvider()->getBadges(7));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnUnknownUserIdGetsNoBadge(): void
    {
        // Arrange
        $this->userRepository->method('find')->willReturn(null);

        // Act + Assert
        $this->assertSame([], $this->makeProvider()->getBadges(7));
    }
}
