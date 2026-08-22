<?php declare(strict_types=1);

namespace Tests\Unit\Filter\Email;

use App\Entity\User;
use App\Filter\Email\AudienceFilterInterface;
use App\Filter\Email\AudienceFilterService;
use PHPUnit\Framework\TestCase;

class AudienceFilterServiceTest extends TestCase
{
    public function testWithNoFilterRegisteredEveryRecipientPassesThrough(): void
    {
        // Arrange
        $recipients = [$this->user('a@example.org'), $this->user('b@example.org')];

        // Act
        $result = new AudienceFilterService([])->installationWideAudience($recipients);

        // Assert
        static::assertSame($recipients, $result);
    }

    public function testFiltersIntersectSoEachOneCanOnlyNarrowTheList(): void
    {
        // Arrange
        $a = $this->user('a@example.org');
        $b = $this->user('b@example.org');
        $c = $this->user('c@example.org');

        $dropsC = $this->filterKeeping([$a, $b]);
        $dropsB = $this->filterKeeping([$a, $c]);

        // Act
        $result = new AudienceFilterService([$dropsC, $dropsB])->installationWideAudience([$a, $b, $c]);

        // Assert
        static::assertSame([$a], $result);
    }

    public function testAFilterCannotWidenTheListItWasGiven(): void
    {
        // Arrange
        $a = $this->user('a@example.org');
        $stranger = $this->user('stranger@example.org');
        $widening = $this->filterKeeping([$a, $stranger]);

        // Act
        $result = new AudienceFilterService([$widening])->installationWideAudience([$a]);

        // Assert
        static::assertSame([$a], $result);
    }

    public function testAnEmptiedListShortCircuitsTheRemainingFilters(): void
    {
        // Arrange
        $empties = $this->filterKeeping([]);
        $neverCalled = $this->createMock(AudienceFilterInterface::class);
        $neverCalled->expects($this->never())->method('filterInstallationWideAudience');

        // Act
        $result = new AudienceFilterService([$empties, $neverCalled])
            ->installationWideAudience([$this->user('a@example.org')]);

        // Assert
        static::assertSame([], $result);
    }

    /** @param list<User> $keep */
    private function filterKeeping(array $keep): AudienceFilterInterface
    {
        return new class($keep) implements AudienceFilterInterface {
            /** @param list<User> $keep */
            public function __construct(private readonly array $keep) {}

            public function filterInstallationWideAudience(array $recipients): array
            {
                return array_values(array_filter(
                    $recipients,
                    fn(User $user) => in_array($user, $this->keep, true),
                ));
            }
        };
    }

    private function user(string $email): User
    {
        return new User()->setEmail($email);
    }
}
