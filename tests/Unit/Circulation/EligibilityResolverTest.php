<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Circulation\EligibilityProviderInterface;
use App\Circulation\EligibilityResolver;
use App\Circulation\EligibilityVerdict;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class EligibilityResolverTest extends TestCase
{
    public function testEmptyChainAllows(): void
    {
        // Arrange
        $resolver = new EligibilityResolver([]);

        // Act
        $verdict = $resolver->resolve('book', 'book', 1, new User());

        // Assert
        self::assertTrue($verdict->allowed);
    }

    public function testAllAbstainingChainAllows(): void
    {
        // Arrange
        $resolver = new EligibilityResolver([$this->provider(null), $this->provider(null)]);

        // Act
        $verdict = $resolver->resolve('book', 'book', 1, new User());

        // Assert
        self::assertTrue($verdict->allowed);
    }

    public function testOneRefusalBlocksAndCarriesItsReason(): void
    {
        // Arrange
        $refusal = EligibilityVerdict::refused('circulation.flash_trust_minimum', ['%required%' => 200]);
        $resolver = new EligibilityResolver([
            $this->provider(EligibilityVerdict::allowed()),
            $this->provider($refusal),
        ]);

        // Act
        $verdict = $resolver->resolve('book', 'book', 1, new User());

        // Assert
        self::assertFalse($verdict->allowed);
        self::assertSame('circulation.flash_trust_minimum', $verdict->reasonKey);
        self::assertSame(['%required%' => 200], $verdict->reasonParams);
    }

    private function provider(?EligibilityVerdict $verdict): EligibilityProviderInterface
    {
        return new class($verdict) implements EligibilityProviderInterface {
            public function __construct(private readonly ?EligibilityVerdict $verdict) {}

            public function canRequest(string $context, string $itemType, int $itemId, User $user): ?EligibilityVerdict
            {
                return $this->verdict;
            }
        };
    }
}
