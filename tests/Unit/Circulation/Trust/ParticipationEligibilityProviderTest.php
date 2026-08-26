<?php declare(strict_types=1);

namespace Tests\Unit\Circulation\Trust;

use App\Circulation\Trust\ContextIndex;
use App\Circulation\Trust\ParticipationEligibilityProvider;
use App\Entity\User;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ParticipationEligibilityProviderTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    public function testItAbstainsWhenTrustDoesNotClaimTheContext(): void
    {
        // Arrange
        $provider = $this->provider(claimed: false, meetsMinimum: false, score: 0, minimum: 200);

        // Act
        $verdict = $provider->canRequest(self::CONTEXT, 'book', 42, $this->user(5));

        // Assert
        self::assertNull($verdict);
    }

    public function testItRefusesBelowTheMinimumAndNamesTheShortfall(): void
    {
        // Arrange
        $provider = $this->provider(claimed: true, meetsMinimum: false, score: 125, minimum: 200);

        // Act
        $verdict = $provider->canRequest(self::CONTEXT, 'book', 42, $this->user(5));

        // Assert
        self::assertNotNull($verdict);
        self::assertFalse($verdict->allowed);
        self::assertSame('circulation.flash_trust_minimum', $verdict->reasonKey);
        self::assertSame(['%required%' => 200, '%current%' => 125], $verdict->reasonParams);
    }

    public function testItAbstainsAtExactlyTheMinimum(): void
    {
        // Arrange
        $provider = $this->provider(claimed: true, meetsMinimum: true, score: 200, minimum: 200);

        // Act
        $verdict = $provider->canRequest(self::CONTEXT, 'book', 42, $this->user(5));

        // Assert
        self::assertNull($verdict);
    }

    private function provider(bool $claimed, bool $meetsMinimum, int $score, int $minimum): ParticipationEligibilityProvider
    {
        $index = $this->createStub(ContextIndex::class);
        $index->method('itemTypeFor')->willReturn($claimed ? 'book' : null);

        $trust = $this->createStub(TrustInterface::class);
        $trust->method('meetsMinimum')->willReturn($meetsMinimum);
        $trust->method('getScore')->willReturn($score);
        $trust->method('getConfig')->willReturn(new TrustConfig(minimumToParticipate: $minimum));

        return new ParticipationEligibilityProvider($index, $trust);
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
