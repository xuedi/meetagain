<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Circulation\ParticipationProviderInterface;
use App\Circulation\ParticipationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParticipationResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{list<bool|null>, bool}>
     */
    public static function chainProvider(): iterable
    {
        yield 'no provider at all circulates nothing' => [[], false];
        yield 'every provider abstains' => [[null, null], false];
        yield 'first non-null wins over a later true' => [[false, true], false];
        yield 'abstention is skipped, not counted as false' => [[null, true], true];
    }

    /**
     * @param list<bool|null> $answers
     */
    #[DataProvider('chainProvider')]
    public function testChain(array $answers, bool $expected): void
    {
        // Arrange
        $resolver = new ParticipationResolver(array_map($this->provider(...), $answers));

        // Act
        $enabled = $resolver->isEnabled('book');

        // Assert
        self::assertSame($expected, $enabled);
    }

    private function provider(?bool $answer): ParticipationProviderInterface
    {
        return new class($answer) implements ParticipationProviderInterface {
            public function __construct(private readonly ?bool $answer) {}

            public function isEnabled(string $itemType): ?bool
            {
                return $this->answer;
            }
        };
    }
}
