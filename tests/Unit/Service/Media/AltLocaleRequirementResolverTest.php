<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Service\Media\AltLocaleRequirementProviderInterface;
use App\Service\Media\AltLocaleRequirementResolver;
use PHPUnit\Framework\TestCase;

class AltLocaleRequirementResolverTest extends TestCase
{
    public function testReturnsFirstNonNullProviderResult(): void
    {
        // Arrange
        $resolver = new AltLocaleRequirementResolver([
            $this->provider(['de', 'en']),
            $this->provider(['fr']),
        ]);

        // Act
        $result = $resolver->getRequiredAltLocales(new Image());

        // Assert
        static::assertSame(['de', 'en'], $result);
    }

    public function testFallsThroughDeferringProvidersToTheDefault(): void
    {
        // Arrange
        $resolver = new AltLocaleRequirementResolver([
            $this->provider(null),
            $this->provider(['de', 'en']),
        ]);

        // Act
        $result = $resolver->getRequiredAltLocales(new Image());

        // Assert
        static::assertSame(['de', 'en'], $result);
    }

    public function testReturnsEmptyWhenEveryProviderDefers(): void
    {
        // Arrange
        $resolver = new AltLocaleRequirementResolver([$this->provider(null)]);

        // Act
        $result = $resolver->getRequiredAltLocales(new Image());

        // Assert
        static::assertSame([], $result);
    }

    /** @param list<string>|null $codes */
    private function provider(?array $codes): AltLocaleRequirementProviderInterface
    {
        return new class($codes) implements AltLocaleRequirementProviderInterface {
            /** @param list<string>|null $codes */
            public function __construct(private readonly ?array $codes) {}

            public function getRequiredAltLocales(Image $image): ?array
            {
                return $this->codes;
            }
        };
    }
}
