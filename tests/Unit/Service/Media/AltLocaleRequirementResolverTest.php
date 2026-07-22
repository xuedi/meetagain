<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Service\Media\AltLocaleRequirementProviderInterface;
use App\Service\Media\AltLocaleRequirementResolver;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

    public function testBatchResolvesEachImageThroughTheFirstNonDeferringProvider(): void
    {
        // Arrange - the first provider claims image 1 only, the second claims everything.
        $imageOne = self::imageWithId(1);
        $imageTwo = self::imageWithId(2);
        $resolver = new AltLocaleRequirementResolver([
            $this->provider(['de'], [1 => ['de'], 2 => null]),
            $this->provider(['en', 'fr'], [2 => ['en', 'fr']]),
        ]);

        // Act
        $result = $resolver->getRequiredAltLocalesForImages([$imageOne, $imageTwo]);

        // Assert
        static::assertSame([1 => ['de'], 2 => ['en', 'fr']], $result);
    }

    public function testBatchFallsBackToEmptyWhenEveryProviderDefers(): void
    {
        // Arrange
        $resolver = new AltLocaleRequirementResolver([$this->provider(null, [])]);

        // Act
        $result = $resolver->getRequiredAltLocalesForImages([self::imageWithId(7)]);

        // Assert
        static::assertSame([7 => []], $result);
    }

    /**
     * @param list<string>|null $codes
     * @param array<int, list<string>|null>|null $batch defaults to $codes for every image
     */
    private function provider(?array $codes, ?array $batch = null): AltLocaleRequirementProviderInterface
    {
        return new class($codes, $batch) implements AltLocaleRequirementProviderInterface {
            /**
             * @param list<string>|null $codes
             * @param array<int, list<string>|null>|null $batch
             */
            public function __construct(
                private readonly ?array $codes,
                private readonly ?array $batch,
            ) {}

            public function getRequiredAltLocales(Image $image): ?array
            {
                return $this->codes;
            }

            public function getRequiredAltLocalesForImages(array $images): array
            {
                if ($this->batch !== null) {
                    return $this->batch;
                }

                $result = [];
                foreach ($images as $image) {
                    $result[(int) $image->getId()] = $this->codes;
                }

                return $result;
            }
        };
    }

    private static function imageWithId(int $id): Image
    {
        $image = new Image();
        $property = new ReflectionProperty(Image::class, 'id');
        $property->setValue($image, $id);

        return $image;
    }
}
